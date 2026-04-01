# Backup System Authorization

## Authorization Strategy

The Backup System uses a **layered authorization approach**:

1. **Route-level**: `admin` middleware on all backup routes
2. **Request-level**: Form Request `authorize()` methods
3. **No Policy needed**: All operations are admin-only (no per-resource checks)

---

## Why This Approach?

### ✅ Advantages

| Reason | Explanation |
|--------|-------------|
| **Consistency** | Matches existing project pattern (admin middleware used throughout) |
| **Simplicity** | No need for Policy when all operations require same permission |
| **Performance** | Middleware runs once per request, not per authorization check |
| **Maintainability** | Authorization logic centralized in middleware + Form Requests |
| **Scalability** | Easy to extend if you need role-based permissions later |

### ❌ Why NOT Policy?

- Policies are best for **per-resource authorization** (e.g., "Can this user edit THIS backup?")
- Backups don't have ownership - they're system-wide admin operations
- Would add unnecessary complexity for simple admin-only checks

### ❌ Why NOT Gates?

- Gates are for **simple boolean checks** without models
- We already have `isAdmin()` method on User model
- Middleware + Form Requests provide better structure

---

## Implementation Details

### 1. Route Protection (Already Applied)

```php
// routes/api.php
Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        Route::prefix('backups')->group(function () {
            Route::get('/', [BackupController::class, 'index']);
            Route::post('/', [BackupController::class, 'store']);
            Route::post('/{id}/restore', [BackupController::class, 'restore']);
            Route::delete('/{id}', [BackupController::class, 'destroy']);
        });
    });
```

**Protection**: 
- `auth:sanctum` → User must be authenticated
- `admin` → User must have `role = 'admin'`

---

### 2. Form Request Authorization (Already Implemented)

#### StoreBackupRequest
```php
public function authorize(): bool
{
    return $this->user()?->isAdmin() ?? false;
}
```

#### RestoreBackupRequest
```php
public function authorize(): bool
{
    return $this->user()?->isAdmin() ?? false;
}
```

**Protection**: Double-checks admin status at request validation level

---

### 3. AdminMiddleware (Existing)

```php
// app/Http/Middleware/AdminMiddleware.php
public function handle(Request $request, Closure $next)
{
    if (!$request->user() || !$request->user()->isAdmin()) {
        return response()->json([
            'message' => 'Unauthorized'
        ], 403);
    }

    return $next($request);
}
```

---

## Authorization Flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Request: POST /admin/backups                             │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 2. Middleware: auth:sanctum                                 │
│    ✓ Check if user is authenticated                         │
│    ✗ Return 401 if not authenticated                        │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 3. Middleware: admin                                        │
│    ✓ Check if user->isAdmin() === true                      │
│    ✗ Return 403 if not admin                                │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 4. Form Request: StoreBackupRequest                         │
│    ✓ authorize() checks isAdmin() again                     │
│    ✓ Validate request data                                  │
│    ✗ Return 403 if authorization fails                      │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ 5. Controller: BackupController@store                       │
│    ✓ Execute business logic via BackupService               │
└─────────────────────────────────────────────────────────────┘
```

---

## Testing Authorization

### Test 1: Unauthenticated User
```bash
curl -X GET http://localhost/api/admin/backups
```
**Expected**: `401 Unauthorized`

### Test 2: Authenticated Non-Admin
```bash
curl -X GET http://localhost/api/admin/backups \
  -H "Authorization: Bearer {user_token}"
```
**Expected**: `403 Forbidden`

### Test 3: Authenticated Admin
```bash
curl -X GET http://localhost/api/admin/backups \
  -H "Authorization: Bearer {admin_token}"
```
**Expected**: `200 OK` with backup list

---

## Future Extensibility

If you need more granular permissions later:

### Option 1: Add Permission Column
```php
// Migration
$table->json('permissions')->nullable();

// User Model
public function can(string $permission): bool
{
    return in_array($permission, $this->permissions ?? []);
}

// Usage
if (!$user->can('backup.restore')) {
    abort(403);
}
```

### Option 2: Use Spatie Permission Package
```bash
composer require spatie/laravel-permission
```

### Option 3: Create BackupPolicy (if needed)
```php
// app/Policies/BackupPolicy.php
public function restore(User $user, BackupHistory $backup): bool
{
    return $user->isAdmin() && $backup->isSuccess();
}

// Controller
$this->authorize('restore', $backup);
```

---

## Summary

✅ **Current Implementation**: Middleware-based (admin only)  
✅ **Best for**: System-wide admin operations  
✅ **Scalable**: Easy to extend with permissions/policies later  
✅ **Consistent**: Matches existing project architecture  

**No additional code needed** - authorization is already complete via:
1. Route middleware (`auth:sanctum`, `admin`)
2. Form Request `authorize()` methods
3. Existing `AdminMiddleware` and `User::isAdmin()` method
