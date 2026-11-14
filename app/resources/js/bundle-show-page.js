function runBundleShowPage() {
    const cfgEl = document.getElementById('bundle-show-config');
    if (!cfgEl) {
        return;
    }

    let cfg;
    try {
        cfg = JSON.parse(cfgEl.textContent);
    } catch {
        return;
    }

    const optionBlocks = cfg.optionBlocks || [];
    const baseSubtotal = Number(cfg.baseSubtotal || 0);
    const baseDiscount = Number(cfg.baseDiscount || 0);
    const baseTotal = Number(cfg.baseTotal || 0);

    const optionsRoot = document.getElementById('bundle-options');
    const optionValuesInput = document.getElementById('bundle-option-value-ids');
    const totalEl = document.getElementById('bundle-price-total');
    const subtotalStatEl = document.getElementById('bundle-price-subtotal-stat');
    const payStatEl = document.getElementById('bundle-price-pay-stat');
    const addBtn = document.querySelector('.bundle-actions .btn-product');

    const priceById = new Map();
    optionBlocks.forEach((block) => {
        (block.values || []).forEach((val) => {
            const id = Number(val.id);
            if (Number.isFinite(id) && id > 0) {
                priceById.set(id, Number(val.price || 0));
            }
        });
    });

    function formatMoney(value) {
        const n = Number(value);
        if (!Number.isFinite(n)) {
            return '0,00';
        }
        return n.toLocaleString('uk-UA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getSelection() {
        if (!optionsRoot) {
            return {};
        }

        const sel = {};
        optionsRoot.querySelectorAll('.opt-group').forEach((group) => {
            const gid = parseInt(group.dataset.groupId, 10);
            if (!Number.isFinite(gid)) {
                return;
            }

            const mode = group.dataset.mode || 'single';
            if (mode === 'multiple') {
                const ids = [];
                group.querySelectorAll('.opt-chip.active, .opt-swatch.active').forEach((el) => {
                    const id = parseInt(el.dataset.valueId, 10);
                    if (Number.isFinite(id)) {
                        ids.push(id);
                    }
                });
                ids.sort((a, b) => a - b);
                if (ids.length) {
                    sel[gid] = ids;
                }
            } else {
                const active = group.querySelector('.opt-chip.active, .opt-swatch.active');
                if (active) {
                    const id = parseInt(active.dataset.valueId, 10);
                    if (Number.isFinite(id)) {
                        sel[gid] = id;
                    }
                }
            }
        });

        return sel;
    }

    function selectedOptionValueIds(selection) {
        const out = [];
        optionBlocks.forEach((block) => {
            const gid = Number(block.id);
            const sv = selection[gid];
            if (sv == null) {
                return;
            }
            if (Array.isArray(sv)) {
                sv.forEach((id) => {
                    const n = Number(id);
                    if (Number.isFinite(n) && n > 0) {
                        out.push(n);
                    }
                });
            } else {
                const n = Number(sv);
                if (Number.isFinite(n) && n > 0) {
                    out.push(n);
                }
            }
        });

        return [...new Set(out)].sort((a, b) => a - b);
    }

    function addonForOptionValueIds(valueIds) {
        let add = 0;
        valueIds.forEach((id) => {
            const p = Number(priceById.get(Number(id)) || 0);
            if (Number.isFinite(p)) {
                add += p;
            }
        });
        return add;
    }

    function selectionIsComplete(selection) {
        if (!optionBlocks.length) {
            return true;
        }

        return optionBlocks.every((block) => {
            const gid = Number(block.id);
            const sv = selection[gid];
            const mode = block.selection_mode || 'single';
            if (mode === 'multiple') {
                return Array.isArray(sv) && sv.length > 0;
            }
            return sv != null && Number.isFinite(Number(sv));
        });
    }

    function refresh() {
        const selection = getSelection();
        const valueIds = selectedOptionValueIds(selection);
        const addon = addonForOptionValueIds(valueIds);
        const subtotal = baseSubtotal + addon;
        const total = baseTotal + addon;
        const complete = selectionIsComplete(selection);

        if (optionValuesInput) {
            optionValuesInput.value = JSON.stringify(valueIds);
        }

        if (totalEl) {
            totalEl.textContent = formatMoney(total);
        }
        if (subtotalStatEl) {
            subtotalStatEl.textContent = formatMoney(subtotal) + ' ₴';
        }
        if (payStatEl) {
            payStatEl.textContent = formatMoney(total) + ' ₴';
        }
        if (addBtn) {
            addBtn.disabled = !complete;
        }
    }

    if (optionsRoot) {
        optionsRoot.querySelectorAll('.opt-chip, .opt-swatch').forEach((el) => {
            el.addEventListener('click', () => {
                const group = el.closest('.opt-group');
                if (!group) {
                    return;
                }

                const mode = group.dataset.mode || 'single';
                if (mode === 'multiple') {
                    el.classList.toggle('active');
                    el.setAttribute('aria-pressed', el.classList.contains('active') ? 'true' : 'false');
                } else {
                    group.querySelectorAll('.opt-chip, .opt-swatch').forEach((node) => {
                        node.classList.remove('active');
                        node.setAttribute('aria-pressed', 'false');
                    });
                    el.classList.add('active');
                    el.setAttribute('aria-pressed', 'true');

                    const exclusivityKey = (group.dataset.exclusivityKey || '').trim();
                    if (exclusivityKey) {
                        optionsRoot.querySelectorAll('.opt-group').forEach((other) => {
                            if (other === group) {
                                return;
                            }
                            if ((other.dataset.exclusivityKey || '').trim() !== exclusivityKey) {
                                return;
                            }
                            other.querySelectorAll('.opt-chip.active, .opt-swatch.active').forEach((node) => {
                                node.classList.remove('active');
                                node.setAttribute('aria-pressed', 'false');
                            });
                        });
                    }
                }

                refresh();
            });
        });
    }

    refresh();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runBundleShowPage);
} else {
    runBundleShowPage();
}
