@php
    $fieldWrapperView = $getFieldWrapperView();
    $googleMapsKey = isset($googleMapsKey) && is_string($googleMapsKey) ? trim($googleMapsKey) : '';
@endphp

<x-dynamic-component :component="$fieldWrapperView" :field="$field">
    @once
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    @endonce

    <div
        class="space-y-2"
        wire:ignore
        x-data="{
            map: null,
            marker: null,
            gmapsKey: @js($googleMapsKey),
            gmapsLoading: false,
            geocodeUrl: @js(route('geocode.address')),
            readCoords() {
                const d = (this.$wire && this.$wire.data) ? this.$wire.data : {};
                if (d.pickup_lat != null && d.pickup_lat !== '' && d.pickup_lng != null && d.pickup_lng !== '') {
                    const la = parseFloat(d.pickup_lat);
                    const ln = parseFloat(d.pickup_lng);
                    if (!isNaN(la) && !isNaN(ln)) return { lat: la, lng: ln };
                }
                return { lat: 49.0, lng: 31.0 };
            },
            syncCoords(lat, lng) {
                const la = Math.round(lat * 1e7) / 1e7;
                const ln = Math.round(lng * 1e7) / 1e7;
                const w = this.$wire;
                if (!w) return;
                if (typeof w.setPickupMapCoords === 'function') {
                    w.setPickupMapCoords(la, ln);
                } else if (typeof w.call === 'function') {
                    w.call('setPickupMapCoords', la, ln);
                }
            },
            bootGoogle(el) {
                const start = this.readCoords();
                this.map = new google.maps.Map(el, {
                    center: { lat: start.lat, lng: start.lng },
                    zoom: 14,
                    gestureHandling: 'greedy',
                    mapTypeControl: true,
                    streetViewControl: false,
                    fullscreenControl: true,
                    clickableIcons: false,
                });
                this.marker = new google.maps.Marker({
                    position: { lat: start.lat, lng: start.lng },
                    map: this.map,
                    draggable: true,
                });
                this.marker.addListener('dragend', () => {
                    const p = this.marker.getPosition();
                    this.syncCoords(p.lat(), p.lng());
                });
                this.map.addListener('click', (ev) => {
                    const p = ev.latLng;
                    this.marker.setPosition(p);
                    this.syncCoords(p.lat(), p.lng());
                });
                setTimeout(() => {
                    if (this.map) google.maps.event.trigger(this.map, 'resize');
                }, 200);
            },
            bootLeaflet(el) {
                if (typeof L === 'undefined' || !el || this.map) return;
                const start = this.readCoords();
                this.map = L.map(el, { scrollWheelZoom: false }).setView([start.lat, start.lng], 14);
                el.addEventListener('wheel', function (e) { e.preventDefault(); }, { passive: false });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap',
                    maxZoom: 19,
                }).addTo(this.map);
                this.marker = L.marker([start.lat, start.lng], { draggable: true }).addTo(this.map);
                this.marker.on('dragend', () => {
                    const p = this.marker.getLatLng();
                    this.syncCoords(p.lat, p.lng);
                });
                this.map.on('click', (ev) => {
                    this.marker.setLatLng(ev.latlng);
                    this.syncCoords(ev.latlng.lat, ev.latlng.lng);
                });
                setTimeout(() => { if (this.map) this.map.invalidateSize(); }, 200);
            },
            boot(el) {
                if (!el || this.map) return;
                if (this.gmapsKey && this.gmapsKey.length > 0) {
                    if (typeof google !== 'undefined' && google.maps) {
                        this.bootGoogle(el);
                        return;
                    }
                    if (this.gmapsLoading) return;
                    this.gmapsLoading = true;
                    const self = this;
                    window.__filamentPickupGmapsLoaded = function () {
                        self.gmapsLoading = false;
                        if (!self.map && el && typeof google !== 'undefined' && google.maps) {
                            self.bootGoogle(el);
                        }
                    };
                    const s = document.createElement('script');
                    s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(this.gmapsKey) + '&callback=__filamentPickupGmapsLoaded';
                    s.async = true;
                    s.defer = true;
                    document.head.appendChild(s);
                    return;
                }
                this.bootLeaflet(el);
            },
            geocodeFromAddress() {
                const addrEl = document.getElementById('filament-pickup-address-field');
                const raw = addrEl && addrEl.value ? String(addrEl.value).trim() : '';
                if (raw.length < 2) return;
                const q = raw + ', Ukraine';
                const self = this;
                fetch(this.geocodeUrl + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then((r) => r.json())
                    .then((j) => {
                        if (!j || j.lat == null || j.lng == null) return;
                        const lat = j.lat;
                        const lng = j.lng;
                        self.syncCoords(lat, lng);
                        if (!self.marker || !self.map) return;
                        if (typeof self.marker.setPosition === 'function') {
                            self.marker.setPosition({ lat: lat, lng: lng });
                            self.map.setCenter({ lat: lat, lng: lng });
                            self.map.setZoom(16);
                        } else if (typeof self.marker.setLatLng === 'function') {
                            self.marker.setLatLng([lat, lng]);
                            self.map.setView([lat, lng], 16);
                        }
                    });
            },
        }"
        x-init="$nextTick(() => { boot($refs.mapEl); })"
    >
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="fi-btn fi-size-sm fi-color-gray relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 fi-btn-color-gray fi-color-gray fi-outlined"
                x-on:click="geocodeFromAddress()"
            >
                Підставити з поля адреси (пошук)
            </button>
        </div>
        <div
            x-ref="mapEl"
            class="w-full rounded-lg border border-gray-200 dark:border-white/10"
            style="min-height: 280px; background: #e8eef2;"
        ></div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Клацніть по карті або перетягніть мітку. Координати оновлюють поля «Широта» та «Довгота» нижче. Кнопка пошуку використовує той самий геокодер, що й чекаут (Nominatim).
            @if ($googleMapsKey !== '')
                <span class="block mt-1">Ключ Maps знайдено — використовується Google Maps.</span>
            @else
                <span class="block mt-1">Ключ Google Maps не заданий у базі та .env — показано карту OpenStreetMap.</span>
            @endif
        </p>
    </div>
</x-dynamic-component>
