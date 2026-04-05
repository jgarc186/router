# Middleware Isolation Ticket Plan

## Ticket
`Router: Middleware applies to ALL routes instead of the last registered`

## Goal
Ensure `middleware()` applies only to the most recently registered route(s), not all existing routes.

## Files Involved
- `src/Garcia/Router.php`
- `test/unit/RouterTest.php`

## Current Problem
- `Router::middleware()` currently loops through all `self::$routes` and appends middleware to each route.
- This causes middleware leakage across unrelated routes.

## Implementation Plan
1. Add state to track the last registered route index(es).
2. Update `addRoute()` to record the index of the route just added.
3. Update `resource()` so the six generated routes are tracked as the latest group.
4. Change `middleware()` to apply only to tracked index(es).
5. Remove dead logic (`isset(...)`) in `middleware()`.
6. Add tests for isolation and resource behavior.

## Suggested Code Direction
In `Router.php`:
- Add a property like:
  - `private static array $lastAddedRouteIndexes = [];`
- In `addRoute()`:
  - Append route.
  - Save last index: `count(self::$routes) - 1`.
- In `resource()`:
  - Capture start index before adding routes.
  - After adding 6 routes, set tracked indexes to that range.
- In `middleware()`:
  - Loop only over `self::$lastAddedRouteIndexes`.
  - Append middleware only for those indexes.
  - Return `$this`.
- In `clearRoutes()`:
  - Reset `self::$lastAddedRouteIndexes` too.

## Test Plan
Add tests in `test/unit/RouterTest.php`:

1. `testMiddlewareAppliesOnlyToLastRegisteredRoute`
- Register `/home` then `/admin`.
- Apply middleware only after `/admin`.
- Assert `/admin` has middleware count `1`.
- Assert `/home` has middleware count `0`.

2. `testSubsequentMiddlewareDoesNotAffectPreviouslyRegisteredRoutes`
- Register route A with middleware A.
- Register route B with middleware B.
- Assert route A still has only middleware A.
- Assert route B has only middleware B.

3. `testResourceMiddlewareAppliesToAllGeneratedRoutesOnly`
- Register a normal route first.
- Register `resource('/tests', ...)` then call `->middleware($mw)`.
- Assert each of the 6 resource routes has middleware count `1`.
- Assert the earlier normal route still has middleware count `0`.

## Validation Steps
1. Run unit tests:
   - `vendor/bin/phpunit`
2. If needed, run targeted tests:
   - `vendor/bin/phpunit test/unit/RouterTest.php`
3. Confirm ticket acceptance criteria:
   - Middleware isolation works.
   - Previous routes are unaffected.
   - `resource()` routes all receive middleware when chained.

## Done Criteria
- All new tests pass.
- Existing tests still pass.
- No middleware leakage to unrelated routes.
