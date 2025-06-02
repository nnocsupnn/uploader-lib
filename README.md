# Maxicare Uploader 🚀

A PHP library for uploading files supported cloud storage.


---

### ❗Required Environment variables for OCI

```php
OCI_REGION=us-phoenix-1
OCI_USER=ocid1.user.oc1..aaaaaaaa...
OCI_FINGERPRINT=12:34:56:78:90:ab:cd:ef...
OCI_TENANCY=ocid1.tenancy.oc1..aaaaaaaa...
OCI_NAMESPACE=your-namespace
OCI_BUCKET_NAME=your-bucket
OCI_KEY_FILE=path/to/private_key.pem
```

---

### ❗Required Environment variables for Hubspot

```php
HUBSPOT_API_KEY=
HUBSPOT_API_FILEUPLOAD= # optional, fallback value is the public api endpoints of hubspot
HUBSPOT_API_DELETE= # optional, fallback value is the public api endpoints of hubspot
HUBSPOT_API_SEARCH= # optional, fallback value is the public api endpoints of hubspot
```
---
### ℹ️ Installation [🔗 Packagist](https://packagist.org/packages/maxicare/uploader)
```bash
composer require maxicare/uploader
```

---


### 🚀 Usage

### For OCI
```php
<?php

namespace MyLaravelApp;

use Maxicare\Uploader;

public function testUpload() {
    $ociUploader = new Uploader();

    $ociUploader->testConnection(); # Test Connection / Configuration

    $ociUploader->upload(file_get_contents(base_path() . "/.dummy/dog.png"), "dog.png"); # Upload using contents to OCI
    $ociUploader->uploadFile(base_path() . "/.dummy/dog.png") # Upload object to OCI
    $ociUploader->download("dog.png"); # Download
    $ociUploader->delete("dog.png"); # Delete
}

```

### For Hubspot
```php
<?php

namespace MyLaravelApp;

use Maxicare\Hubspot;

public function testUpload() {
    $hubspot = new Hubspot();
    $hubspot->upload(base_path() . "/.dummy/dog.png", "folderId1234"); # Upload using contents to OCI

    $files = $hubspot->files("folderId1234", "sometext_name")->results();
    $filesNext = $hubspot->files("folderId1234", "sometext_name")->next()->results();
}

```

### 📝License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).