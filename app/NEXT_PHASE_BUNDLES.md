# Next Phase: Bundles and Dynamic Discounts

This file defines the next implementation stage after MVP.

## Goals

- Add bundle products (zoo kits) that include multiple listings or regular products.
- Apply dynamic bundle pricing rules when one bundle item has a discount.
- Keep pricing transparent in admin panel and storefront.

## Data Model Extension

- `bundles`
  - `id`, `title`, `slug`, `description`, `is_visible`, `is_active`, timestamps
- `bundle_items`
  - `id`, `bundle_id`, `animal_listing_id` (or `product_id` later), `qty`, `sort_order`
- Знижки на комплекти: існуюча таблиця `promotion_targets` з `target_type = bundle` та `target_id = bundles.id` (дати та активність — у `promotions`).

## Pricing Rules

1. Listing effective price = base price minus active listing discounts.
2. Bundle subtotal = sum of all bundle item effective prices multiplied by quantity.
3. Bundle-level discount is applied on top of subtotal.
4. Final bundle price is never below zero.

## Admin Scope

- Filament resource `BundleResource` with bundle items repeater.
- Знижки на комплекти: позиції в акції з типом цілі «Комплект» (`promotion_targets.target_type = bundle`), без окремого ресурсу правил.
- Preview field for computed bundle price in admin form/table.

## Frontend Scope

- Show old/new price when discount is active.
- Show what discount is applied (item or bundle).
- Ensure checkout stores pricing snapshots in order items.
