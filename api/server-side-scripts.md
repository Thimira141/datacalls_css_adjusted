# 🛡️ `visudo` Configuration for PHP-Asterisk Integration

**Author:** Thimira Dilhan  
**Purpose:** Grants `www-data` and `asterisk` users permission to execute specific shell scripts via `sudo` without password prompts. Enables backend call control, dialplan management, audio conversion, and call status retrieval from PHP.

---

## 🔧 Location

Configured via:

```bash
sudo visudo
```

---

## 🧩 Entries

```bash
# Reload dialplan from PHP TTS handler
asterisk ALL=(ALL) NOPASSWD: /usr/local/bin/reload_dialplan.sh

# Make call via shell from call API
asterisk ALL=(ALL) NOPASSWD: /usr/local/bin/asterisk_call.sh

# End call via shell from call API
asterisk ALL=(ALL) NOPASSWD: /usr/local/bin/asterisk_call_end.sh

# Get call status from shell via call API
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/channel_status.sh

# Convert audio and set permissions via TTS API
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/convert_audio.sh
```

---

## ✅ Notes

- Each script is scoped to a specific PHP endpoint:
  - `/var/www/html/mbilling/api/tts/index.php` → TTS conversion and dialplan reload
  - `/var/www/html/mbilling/api/call/index.php` → Call control and status

- Scripts must be executable:

  ```bash
  chmod +x /usr/local/bin/*.sh
  ```

- PHP calls should use:

  ```php
  exec("sudo /usr/local/bin/script.sh args...", $output, $returnVar);
  ```

- This setup ensures secure, passwordless execution of backend tasks from web-facing PHP scripts.
