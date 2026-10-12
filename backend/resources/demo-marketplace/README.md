# Original demo artwork

Catalog: 20 fictional Vietnamese secondhand listings. Images: 24 original code-drawn illustrations, clearly labeled as demo artwork; no third-party photos or hotlinks.

Committed assets are WebP, 960×720, **263,372 bytes total**. Production seeding copies them into Laravel public storage using valid deterministic UUID filenames. Exactly one primary image is inserted per new product.

Optional development regeneration (Python + Pillow):

```powershell
python backend/resources/demo-marketplace/render_assets.py
```

Pillow is a developer-only regeneration tool; it is not an application/runtime dependency. The normal seeder uses committed image files. Inventory, commands, preservation/reset behavior and verification are documented in [demo data pack](../../../docs/demo-data-pack.md).
