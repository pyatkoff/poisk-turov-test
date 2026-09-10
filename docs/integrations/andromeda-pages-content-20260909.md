Andromeda pagination and hotel content — 2026-09-09

The scoped public test route remains https://anytoour.ru/_preview/search3-anex-candidate/poisk-turov/.
Current deployed runtime: 365e30fa11dc86d0e7750d16ca52c9d72661120c. Pagination/content deployment run34380113098, continuation correction run34381046363. No production/whole-site release replacement.

The UI requests pages sequentially, retains prior offers on failure and merges offers by provider plus stable offer_ref. Unresolved hotels use source-qualified keys and receive supplier names/location/category/content, with no fabricated local ID. Accepted mapped hotels continue using the existing catalog. Explicit catalog-dependent filters omit unresolved identities.

The owner states a SAMO quota of 5,000,000 requests/month. The old temporary 6/min cap has been removed. A private UTC calendar-month counter covers actual login/price attempts through this integration only; it does not claim historical account-wide billing accuracy. Completed page reads use the saved response; subsequent pages reuse the private supplier session.

SAMO search documentation explicitly defines hotelImage and hotelUrl:
https://dokuwiki.samo.ru/doku.php?id=andromeda:search
The saved 50 Anex offers have 46 image URLs and 50 detail URLs (audit34379474359/artifact10115236208). Full textual descriptions and galleries are not part of this price contract. Standalone cards have a supplier detail link and a supplier image fallback; direct image rendering has not been verified: an inspected image fell back after load. Do not claim the supplier gallery has been imported.

Owner-supplied ANEX media URL:
https://files.anextour.ru/hotel/egypt/hotel/sharming-inn-hotel-sharm-el-sheikh/o417822?&hotelCode=5844
The explicit hotelCode5844 matches the middle component of the saved Andromeda image5.5844.3414.jpg. This corroborates ANEX5844 ↔ Andromeda3414 ↔ already accepted local Tourvisor453 for Swiss Heaven Sharming Inn. This is one identified example, not proof of a universal filename contract. The external media response could not be fetched; provenance is the user-provided URL. No new mapping rows were written during this content step.

The saved Egypt catalog remains 905 identities:584accepted/318pending/3conflict. Other countries and unsupported form filters remain to implement. Operator exclusions remain off: owner reports Anex, Intourist, Fun&Sun and BiblioGlobus enabled in Andromeda. Actual offers depend on query availability. Quote/selection/booking remain disabled.

The first live attempt retained13pages/107hotels after a continuation failure. The supplier total changed from46 to39 during that search. The follow-up now uses the immediately previous completed page count to authorize continuation and does not misclassify missing page context as unsupported form criteria. Do not replay unknown supplier outcomes. Full current browser acceptance is recorded in reports/andromeda-live-preview-20260909.json.

Final browser acceptance:39/39pages complete,218Andromeda hotels,62unresolved cards; source filter218/251combined. Actual operator labels Anex Tour, Intourist, Fun&Sun. BiblioGlobus not observed in this query; no exclusion was introduced.
