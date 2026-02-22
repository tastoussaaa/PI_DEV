# TODO List

## Fix: Warning Undefined array key "conceptProperties"

### Problem
In `src/Service/MedicationApiService.php`, the `searchMedications` method tries to access `$group['conceptProperties']` without checking if the key exists, causing PHP warnings when the RxNorm API returns concept groups without this key.

### Plan
- [x] Identify the issue in `searchMedications` method (line 28)
- [x] Fix the issue by adding proper isset check before accessing conceptProperties

### Fix Details
In `searchMedications` method, add isset check before the foreach loop that accesses conceptProperties:
```
php
// Before (problematic):
foreach ($group['conceptProperties'] as $concept) {

// After (fixed):
if (isset($group['conceptProperties'])) {
    foreach ($group['conceptProperties'] as $concept) {
```

Note: The `getMedicationDetails` method already has this fix properly implemented.
