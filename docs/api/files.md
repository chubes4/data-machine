# Files Endpoint

**Implementation**: `inc/Api/Files.php`

**Base URL**: `/wp-json/datamachine/v1/files`

## Overview

The Files endpoint handles file uploads for pipeline processing with flow-isolated storage, security validation, and automatic URL generation.

## Authentication

Requires `manage_options` capability. See [Authentication Guide](authentication.md).

## Endpoints

### POST /files

Upload a file for pipeline processing.

**Permission**: `manage_options` capability required

**Parameters**:
- `flow_step_id` (string, required): Flow step ID to associate with upload
- `file` (file, required): File to upload (multipart/form-data)

**File Restrictions**:
- **Maximum size**: 32MB
- **Blocked extensions**: php, exe, bat, js, sh, and other executable types
- **Security**: Path traversal protection and MIME type validation

**Example Request**:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/files \
  -u username:application_password \
  -F "flow_step_id=abc-123_42" \
  -F "file=@/path/to/document.pdf"
```

**Success Response (201 Created)**:

```json
{
  "success": true,
  "file_info": {
    "filename": "document_1234567890.pdf",
    "size": 1048576,
    "modified": 1704153600,
    "url": "https://example.com/wp-content/uploads/data-machine-files/abc-123_42/document_1234567890.pdf"
  },
  "message": "File \"document.pdf\" uploaded successfully."
}
```

**Response Fields**:
- `success` (boolean): Request success status
- `file_info` (object): Uploaded file information
  - `filename` (string): Timestamped filename
  - `size` (integer): File size in bytes
  - `modified` (integer): Unix timestamp of upload
  - `url` (string): Public URL to access file
- `message` (string): Success confirmation

## Error Responses

### 400 Bad Request - File Too Large

```json
{
  "code": "file_validation_failed",
  "message": "File too large: 50 MB. Maximum allowed size: 32 MB",
  "data": {"status": 400}
}
```

### 400 Bad Request - Invalid File Type

```json
{
  "code": "file_validation_failed",
  "message": "File type not allowed for security reasons.",
  "data": {"status": 400}
}
```

### 400 Bad Request - Missing Parameters

```json
{
  "code": "missing_required_param",
  "message": "Missing required parameter: flow_step_id",
  "data": {"status": 400}
}
```

### 400 Bad Request - No File Uploaded

```json
{
  "code": "no_file_uploaded",
  "message": "No file was uploaded.",
  "data": {"status": 400}
}
```

## File Storage

### Directory Structure

Files are stored in flow-isolated directories:

```
wp-content/uploads/data-machine-files/
└── {flow_step_id}/
    ├── document_1234567890.pdf
    ├── image_1234567891.jpg
    └── data_1234567892.csv
```

### Filename Format

Uploaded files are automatically timestamped to prevent collisions:

```
{original_name}_{unix_timestamp}.{extension}
```

**Example**: `report.pdf` → `report_1704153600.pdf`

### Access Control

Files are stored in publicly accessible directories but organized by flow step ID for isolation and management.

## Security Features

### Blocked File Types

The following file extensions are blocked for security:

- Executables: `exe`, `bat`, `sh`, `cmd`, `com`
- Scripts: `php`, `phtml`, `php3`, `php4`, `php5`, `phps`
- Web: `js`, `jsp`, `asp`, `aspx`
- Archives with code: `jar`
- Config: `htaccess`

### MIME Type Validation

Server validates MIME types to prevent file type spoofing:

```php
// Allowed MIME types (examples)
- application/pdf
- image/jpeg, image/png, image/gif
- text/csv
- application/json
- etc.
```

### Path Traversal Protection

File paths are sanitized to prevent directory traversal attacks:

```php
// Blocked patterns
- ../
- ..\\
- Absolute paths
```

## Integration Examples

### Python File Upload

```python
import requests
from requests.auth import HTTPBasicAuth

url = "https://example.com/wp-json/datamachine/v1/files"
auth = HTTPBasicAuth("username", "application_password")

# Upload file
with open('/path/to/document.pdf', 'rb') as f:
    files = {'file': f}
    data = {'flow_step_id': 'abc-123_42'}

    response = requests.post(url, files=files, data=data, auth=auth)

if response.status_code == 201:
    result = response.json()
    print(f"File uploaded: {result['file_info']['url']}")
else:
    print(f"Upload failed: {response.json()['message']}")
```

### JavaScript File Upload

```javascript
const axios = require('axios');
const FormData = require('form-data');
const fs = require('fs');

async function uploadFile(filePath, flowStepId) {
  const form = new FormData();
  form.append('file', fs.createReadStream(filePath));
  form.append('flow_step_id', flowStepId);

  const response = await axios.post(
    'https://example.com/wp-json/datamachine/v1/files',
    form,
    {
      auth: {
        username: 'admin',
        password: 'application_password'
      },
      headers: form.getHeaders()
    }
  );

  return response.data.file_info.url;
}

// Usage
const fileUrl = await uploadFile('/path/to/document.pdf', 'abc-123_42');
console.log(`File URL: ${fileUrl}`);
```

### cURL with Form Data

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/files \
  -u username:application_password \
  -F "flow_step_id=abc-123_42" \
  -F "file=@/Users/username/Documents/report.pdf"
```

## Common Use Cases

### CSV Data Import

Upload CSV files for processing by fetch handlers:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/files \
  -u username:application_password \
  -F "flow_step_id=fetch-step_42" \
  -F "file=@/path/to/data.csv"
```

### Image Processing

Upload images for AI analysis or publishing:

```bash
curl -X POST https://example.com/wp-json/datamachine/v1/files \
  -u username:application_password \
  -F "flow_step_id=ai-step_42" \
  -F "file=@/path/to/image.jpg"
```

### Batch Document Processing

Upload multiple documents for workflow processing:

```bash
for file in /path/to/documents/*.pdf; do
  curl -X POST https://example.com/wp-json/datamachine/v1/files \
    -u username:application_password \
    -F "flow_step_id=fetch-step_42" \
    -F "file=@$file"
done
```

## File Lifecycle

1. **Upload**: File uploaded via REST API
2. **Validation**: Size, type, and security checks
3. **Storage**: Saved to flow-isolated directory
4. **Processing**: Fetch handler accesses file via URL
5. **Cleanup**: Manual cleanup or automated via flow deletion

## Related Documentation

- [Execute Endpoint](execute.md) - Workflow execution
- [Flows Endpoints](flows.md) - Flow management
- [Handlers Endpoint](handlers.md) - Available handlers
- [Authentication](authentication.md) - Auth methods

---

**Base URL**: `/wp-json/datamachine/v1/files`
**Permission**: `manage_options` capability required
**Implementation**: `inc/Api/Files.php`
**Max File Size**: 32MB
