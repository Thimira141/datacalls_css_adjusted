# 🛠 Step-by-Step Guide: Create `/usr/local/bin/reload_dialplan.sh` and Run It via PHP

## 📁 1. Create the Shell Script

**Path:** `/usr/local/bin/reload_dialplan.sh`  
**Purpose:** Safely reload Asterisk dialplan from PHP

### ✅ Script Contents

```bash
#!/bin/bash
sudo /usr/sbin/asterisk -rx "dialplan reload"
```

### 🔐 Permissions & Safety

- Make the script executable:

  ```bash
  chmod +x /usr/local/bin/reload_dialplan.sh
  ```

- Add a secure sudoers rule to allow web server user (e.g., `asterisk`) to run it without password:

  ```bash
  visudo
  ```

  Add:

  ```
  asterisk ALL=(ALL) NOPASSWD: /usr/local/bin/reload_dialplan.sh
  ```

## 🧪 2. Test Script Manually

Run as web server user to confirm:

```bash
sudo -u asterisk /usr/local/bin/reload_dialplan.sh
```

## 🧩 3. PHP Integration

### 🧼 PHP Wrapper Function

```php
// Reload dialplan
    exec("sudo /usr/local/bin/reload_dialplan.sh", $output, $returnVar);
    if ($returnVar !== 0) {
        json_error('Failed to reload Asterisk dialplan' . implode("\n", $output) . '; rtVar: ' . $returnVar);
    }
```
