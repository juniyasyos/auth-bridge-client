Software Bill of Materials (SBOM)

This repository is licensed under a proprietary license. Maintain a Software Bill of Materials (SBOM) to track included components.

Recommended tools to generate an SBOM:

- Syft (Anchore): `syft -o cyclonedx-json . > sbom-cyclonedx.json`
- CycloneDX Composer plugin (for PHP/Composer): https://github.com/CycloneDX/cyclonedx-php-composer

Example commands:

```
# Using Syft
syft . -o cyclonedx-json > docs/sbom-cyclonedx.json

# Using Composer plugin (if installed)
composer cyclonedx:make --output docs/sbom-cyclonedx.json
```

Place generated SBOM files in `docs/` and reference them here.
