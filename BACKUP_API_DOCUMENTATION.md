# Backup System API Documentation

Complete API reference for the Laravel Backup System endpoints.

---

## Base URL

```
https://api.example.com/admin/backups
```

## Authentication

All endpoints require:
- **Authentication:** Bearer token (Sanctum)
- **Authorization:** Admin role
- **Headers:**
  - `Authorization: Bearer {token}`
  - `Content-Type: application/json`
  - `x-lang: en` or `x-lang: ar` (optional, for localized responses)

---

## Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/backups` | List all backups with filters |
| POST | `/admin/backups` | Create a new backup |
| POST | `/admin/backups/{id}/restore` | Restore a backup by ID |
| DELETE | `/admin/backups/{id}` | Delete a backup by ID |
| GET | `/admin/backups/diagnostics` | Run system diagnostics |
| GET | `/admin/backups/statistics` | Get backup statistics |
| POST | `/admin/backups/diagnostics/fix` | Auto-fix common issues |

---

## 1. List Backups

Retrieve a paginated list of all backups with optional filters.

### Request

**Method:** `GET`

**URL:** `/admin/backups`

**Auth Required:** Yes (Admin only)

**Query Parameters:**

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| `status` | string | No | Filter by status: `pending`, `success`, `failed` | `success` |
| `type` | string | No | Filter by type: `db`, `files`, `full` | `full` |
| `days` | integer | No | Filter backups from last N days | `7` |
| `per_page` | integer | No | Items per page (default: 15) | `20` |

### Example Request

```bash
curl -X GET "https://api.example.com/admin/backups?status=success&type=full&days=30&per_page=10" \
  -H "Authorization: Bearer {token}" \
  -H "x-lang: en"
```

### Success Response

**Status Code:** `200 OK`

```json
{
  "ok": true,
  "message": "Backups retrieved successfully.",
  "data": [
    {
      "id": 15,
      "file_name": "backup_full_20260331_120000.zip",
      "file_path": "backups/2026/03/backup_full_20260331_120000.zip",
      "type": "full",
      "status": "success",
      "size": 2147483648,
      "created_by": 1,
      "created_at": "2026-03-31T12:00:00Z",
      "completed_at": "2026-03-31T12:15:30Z",
      "error_message": null,
      "creator": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
      }
    },
    {
      "id": 14,
      "file_name": "backup_db_20260330_080000.sql.gz",
      "file_path": "backups/2026/03/backup_db_20260330_080000.sql.gz",
      "type": "db",
      "status": "success",
      "size": 524288000,
      "created_by": 1,
      "created_at": "2026-03-30T08:00:00Z",
      "completed_at": "2026-03-30T08:05:12Z",
      "error_message": null,
      "creator": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 10,
    "total": 45,
    "last_page": 5
  }
}
```

### Error Response

**Status Code:** `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "Failed to retrieve backups.",
  "error": "Database connection failed"
}
```

---

## 2. Create Backup

Create a new backup of the specified type (database, files, or full).

### Request

**Method:** `POST`

**URL:** `/admin/backups`

**Auth Required:** Yes (Admin only)

**Request Body:**

| Field | Type | Required | Description | Valid Values |
|-------|------|----------|-------------|--------------|
| `type` | string | Yes | Type of backup to create | `db`, `files`, `full` |

### Example Request

```bash
curl -X POST "https://api.example.com/admin/backups" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "x-lang: en" \
  -d '{
    "type": "full"
  }'
```

### Success Response

**Status Code:** `201 Created`

```json
{
  "ok": true,
  "message": "Backup created successfully.",
  "data": {
    "id": 16,
    "file_name": "backup_full_20260331_140000.zip",
    "type": "full",
    "status": "success",
    "size": "2.1 GB",
    "duration": "15 minutes 30 seconds",
    "created_by": "Admin User",
    "created_at": "2026-03-31T14:00:00Z",
    "completed_at": "2026-03-31T14:15:30Z"
  }
}
```

### Error Responses

**Status Code:** `422 Unprocessable Entity` (Invalid Type)

```json
{
  "ok": false,
  "message": "Invalid backup type provided.",
  "error": "Type must be one of: db, files, full"
}
```

**Status Code:** `422 Unprocessable Entity` (Validation Error)

```json
{
  "ok": false,
  "message": "The given data was invalid.",
  "errors": {
    "type": [
      "Backup type is required.",
      "Backup type must be one of: db, files, or full."
    ]
  }
}
```

**Status Code:** `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "Failed to create backup.",
  "error": "mysqldump command not found"
}
```

---

## 3. Restore Backup

Restore a backup by its ID. This operation creates a safety backup before restoration.

### Request

**Method:** `POST`

**URL:** `/admin/backups/{id}/restore`

**Auth Required:** Yes (Admin only)

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | The backup ID to restore |

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `confirm` | boolean | Yes | Must be `true` to confirm restoration |

### Example Request

```bash
curl -X POST "https://api.example.com/admin/backups/15/restore" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -H "x-lang: en" \
  -d '{
    "confirm": true
  }'
```

### Success Response

**Status Code:** `200 OK`

```json
{
  "ok": true,
  "message": "Backup restored successfully."
}
```

### Error Responses

**Status Code:** `404 Not Found`

```json
{
  "ok": false,
  "message": "Backup not found."
}
```

**Status Code:** `422 Unprocessable Entity` (Cannot Restore)

```json
{
  "ok": false,
  "message": "Cannot restore this backup.",
  "error": "Backup file does not exist on disk"
}
```

**Status Code:** `422 Unprocessable Entity` (Validation Error)

```json
{
  "ok": false,
  "message": "The given data was invalid.",
  "errors": {
    "confirm": [
      "Restore confirmation is required.",
      "You must confirm the restore operation."
    ]
  }
}
```

**Status Code:** `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "An error occurred during restoration.",
  "error": "Failed to extract backup archive"
}
```

---

## 4. Delete Backup

Delete a backup by its ID. This removes both the database record and the physical file.

### Request

**Method:** `DELETE`

**URL:** `/admin/backups/{id}`

**Auth Required:** Yes (Admin only)

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | The backup ID to delete |

### Example Request

```bash
curl -X DELETE "https://api.example.com/admin/backups/15" \
  -H "Authorization: Bearer {token}" \
  -H "x-lang: en"
```

### Success Response

**Status Code:** `200 OK`

```json
{
  "ok": true,
  "message": "Backup deleted successfully."
}
```

### Error Responses

**Status Code:** `404 Not Found`

```json
{
  "ok": false,
  "message": "Backup not found."
}
```

**Status Code:** `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "Failed to delete backup.",
  "error": "File deletion failed"
}
```

---

## 5. Run Diagnostics

Run a comprehensive health check on the backup system.

### Request

**Method:** `GET`

**URL:** `/admin/backups/diagnostics`

**Auth Required:** Yes (Admin only)

### Example Request

```bash
curl -X GET "https://api.example.com/admin/backups/diagnostics" \
  -H "Authorization: Bearer {token}" \
  -H "x-lang: en"
```

### Success Response

**Status Code:** `200 OK`

```json
{
  "ok": true,
  "message": "Backup diagnostics completed successfully.",
  "data": {
    "status": "healthy",
    "timestamp": "2026-03-31T14:30:00Z",
    "summary": {
      "issues": 0,
      "warnings": 1,
      "info": 5
    },
    "issues": [],
    "warnings": [
      {
        "code": "disk_space_low",
        "message": "Less than 5GB free space",
        "details": {
          "free": "4.2 GB",
          "used_percent": "85.5%"
        }
      }
    ],
    "info": [
      {
        "code": "storage_ok",
        "message": "Backup directory is writable"
      },
      {
        "code": "database_ok",
        "message": "Database connection successful"
      },
      {
        "code": "mysqldump_ok",
        "message": "mysqldump is available",
        "details": {
          "path": "/usr/bin/mysqldump"
        }
      },
      {
        "code": "no_orphaned_records",
        "message": "All backup records have corresponding files"
      },
      {
        "code": "backup_health_ok",
        "message": "Recent successful backup exists",
        "details": {
          "last_backup": "2026-03-31 12:00:00",
          "hours_ago": 2
        }
      }
    ],
    "statistics": {
      "total_backups": 45,
      "successful": 42,
      "failed": 2,
      "pending": 1,
      "total_size": "25.3 GB",
      "oldest_backup": "2026-01-15T10:30:00Z",
      "newest_backup": "2026-03-31T12:00:00Z",
      "by_type": {
        "db": 20,
        "files": 15,
        "full": 10
      }
    }
  }
}
```

### Error Response

**Status Code:** `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "Failed to run backup diagnostics.",
  "error": "Service unavailable"
}
```

---

## 6. Get Statistics

Retrieve backup statistics without running full diagnostics.

### Request

**Method:** `GET`

**URL:** `/admin/backups/statistics`

**Auth Required:** Yes (Admin only)

### Example Request

```bash
curl -X GET "https://api.example.com/admin/backups/statistics" \
  -H "Authorization: Bearer {token}" \
  -H "x-lang: en"
```

### Success Response

**Status Code:** `200 OK`

```json
{
  "ok": true,
  "message": "Backup statistics retrieved successfully.",
  "data": {
    "total_backups": 45,
    "successful": 42,
    "failed": 2,
    "pending": 1,
    "total_size": "25.3 GB",
    "oldest_backup": "2026-01-15T10:30:00Z",
    "newest_backup": "2026-03-31T12:00:00Z",
    "by_type": {
      "db": 20,
      "files": 15,
      "full": 10
    }
  }
}
```

### Error Response

**Status Code:** `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "Failed to retrieve backup statistics.",
  "error": "Database query failed"
}
```

---

## 7. Auto-Fix Issues

Automatically fix common backup issues (orphaned records and stuck backups).

### Request

**Method:** `POST`

**URL:** `/admin/backups/diagnostics/fix`

**Auth Required:** Yes (Admin only)

### Example Request

```bash
curl -X POST "https://api.example.com/admin/backups/diagnostics/fix" \
  -H "Authorization: Bearer {token}" \
  -H "x-lang: en"
```

### Success Response

**Status Code:** `200 OK`

```json
{
  "ok": true,
  "message": "Auto-fix completed successfully.",
  "data": {
    "orphaned_records_fixed": 3,
    "stuck_backups_fixed": 1,
    "total_fixed": 4
  }
}
```

### Error Response

**Status Code:** `500 Internal Server Error`

```json
{
  "ok": false,
  "message": "Failed to run auto-fix.",
  "error": "Database update failed"
}
```

---

## Response Structure

All API responses follow a consistent structure:

### Success Response
```json
{
  "ok": true,
  "message": "Human-readable success message",
  "data": { /* Response data */ },
  "meta": { /* Pagination metadata (if applicable) */ }
}
```

### Error Response
```json
{
  "ok": false,
  "message": "Human-readable error message",
  "error": "Technical error details",
  "errors": { /* Validation errors (if applicable) */ }
}
```

---

## HTTP Status Codes

| Code | Description | When Used |
|------|-------------|-----------|
| 200 | OK | Successful GET, DELETE, or action |
| 201 | Created | Successful POST (resource created) |
| 401 | Unauthorized | Missing or invalid authentication |
| 403 | Forbidden | User lacks admin privileges |
| 404 | Not Found | Backup ID does not exist |
| 422 | Unprocessable Entity | Validation error or business logic error |
| 500 | Internal Server Error | Server-side error |

---

## Localization

All messages support localization via the `x-lang` header:

**English:**
```bash
-H "x-lang: en"
```

**Arabic:**
```bash
-H "x-lang: ar"
```

### Example Localized Response (Arabic)

```json
{
  "ok": true,
  "message": "تم جلب النسخ الاحتياطية بنجاح.",
  "data": [...]
}
```

---

## Rate Limiting

- **Limit:** 60 requests per minute per user
- **Headers:**
  - `X-RateLimit-Limit: 60`
  - `X-RateLimit-Remaining: 45`
  - `X-RateLimit-Reset: 1711891200`

When rate limit is exceeded:

**Status Code:** `429 Too Many Requests`

```json
{
  "ok": false,
  "message": "Too many requests. Please try again later."
}
```

---

## Common Use Cases

### 1. Create and Monitor Backup

```bash
# Step 1: Create backup
RESPONSE=$(curl -X POST "https://api.example.com/admin/backups" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"type": "full"}')

BACKUP_ID=$(echo $RESPONSE | jq -r '.data.id')

# Step 2: Check status
curl -X GET "https://api.example.com/admin/backups?status=success" \
  -H "Authorization: Bearer {token}"
```

### 2. Run Diagnostics and Auto-Fix

```bash
# Step 1: Run diagnostics
curl -X GET "https://api.example.com/admin/backups/diagnostics" \
  -H "Authorization: Bearer {token}"

# Step 2: Auto-fix if issues found
curl -X POST "https://api.example.com/admin/backups/diagnostics/fix" \
  -H "Authorization: Bearer {token}"
```

### 3. Restore Latest Backup

```bash
# Step 1: Get latest backup
LATEST=$(curl -X GET "https://api.example.com/admin/backups?per_page=1" \
  -H "Authorization: Bearer {token}" | jq -r '.data[0].id')

# Step 2: Restore it
curl -X POST "https://api.example.com/admin/backups/${LATEST}/restore" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"confirm": true}'
```

---

## Error Handling Best Practices

1. **Always check the `ok` field** before processing data
2. **Display the `message` field** to users (it's localized)
3. **Log the `error` field** for debugging (technical details)
4. **Handle validation errors** from the `errors` object
5. **Implement retry logic** for 500 errors with exponential backoff

### Example Error Handling (JavaScript)

```javascript
async function createBackup(type) {
  try {
    const response = await fetch('/admin/backups', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'x-lang': 'en'
      },
      body: JSON.stringify({ type })
    });

    const data = await response.json();

    if (!data.ok) {
      // Handle error
      console.error('Error:', data.error);
      alert(data.message); // Show localized message to user
      
      // Handle validation errors
      if (data.errors) {
        Object.keys(data.errors).forEach(field => {
          console.error(`${field}:`, data.errors[field]);
        });
      }
      
      return null;
    }

    // Success
    console.log('Backup created:', data.data);
    return data.data;

  } catch (error) {
    console.error('Network error:', error);
    alert('Failed to connect to server');
    return null;
  }
}
```

---

## Testing with Postman

### Collection Setup

1. **Create Environment Variables:**
   - `base_url`: `https://api.example.com`
   - `token`: Your admin bearer token
   - `lang`: `en` or `ar`

2. **Set Headers (Collection Level):**
   ```
   Authorization: Bearer {{token}}
   Content-Type: application/json
   x-lang: {{lang}}
   ```

3. **Import Requests:**
   - GET List Backups: `{{base_url}}/admin/backups`
   - POST Create Backup: `{{base_url}}/admin/backups`
   - POST Restore Backup: `{{base_url}}/admin/backups/:id/restore`
   - DELETE Delete Backup: `{{base_url}}/admin/backups/:id`
   - GET Diagnostics: `{{base_url}}/admin/backups/diagnostics`
   - GET Statistics: `{{base_url}}/admin/backups/statistics`
   - POST Auto-Fix: `{{base_url}}/admin/backups/diagnostics/fix`

---

## Security Considerations

1. **Authentication Required:** All endpoints require valid Sanctum token
2. **Admin Authorization:** Only users with admin role can access
3. **HTTPS Only:** All requests must use HTTPS in production
4. **CSRF Protection:** Not required for API routes (Sanctum handles this)
5. **Rate Limiting:** 60 requests per minute per user
6. **Input Validation:** All inputs are validated before processing
7. **SQL Injection Protection:** Using Eloquent ORM and parameterized queries
8. **File Path Validation:** Backup paths are validated to prevent directory traversal

---

## Related Documentation

- [BACKUP_DIAGNOSTICS.md](BACKUP_DIAGNOSTICS.md) - Diagnostics feature guide
- [BACKUP_AUTHORIZATION.md](BACKUP_AUTHORIZATION.md) - Authorization strategy
- [BACKUP_LOGGING_EVENTS.md](BACKUP_LOGGING_EVENTS.md) - Events and logging
- [BACKUP_SYSTEM_COMPLETE.md](BACKUP_SYSTEM_COMPLETE.md) - Complete system overview

---

## Support

For issues or questions:
- Check the diagnostics endpoint for system health
- Review Laravel logs: `storage/logs/laravel.log`
- Run CLI diagnostics: `php artisan backup:diagnose`
- Contact system administrator

---

**Last Updated:** March 31, 2026  
**API Version:** 1.0  
**Laravel Version:** 12.x
