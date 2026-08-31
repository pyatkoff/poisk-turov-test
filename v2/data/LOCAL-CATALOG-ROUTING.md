# Local catalog routing

Advanced hotel options are served from `catalog_hotels` through `hotels-select-v1.php` when country and region are selected. Selected subregion, category, rating and the first hotel type are applied locally. Any local endpoint failure falls back to the existing Tourvisor API through `V2Runtime.api`.
