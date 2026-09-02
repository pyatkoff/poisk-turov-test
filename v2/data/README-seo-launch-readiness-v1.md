# SEO2 launch-readiness report

Read-only CLI for reviewing an existing SEO catalog without changing routes, robots, sitemap, publication candidates or launch flags.

Example without hotel evidence (hotel-tour rows fail closed):

```bash
php v2/data/report-seo-launch-readiness-v1.php \
  --catalog-file=v2/seo-content-pilot-maldives-catalog-v1.php \
  --catalog-function=v2_seo_content_pilot_maldives_catalog \
  </dev/null
```

Fresh identity-only hotel evidence can be supplied by file or stdin. Use `--require-ready=N` only as a review gate; it exits non-zero when fewer than N pages are 100/100 ready. The command never publishes pages.