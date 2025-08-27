# 📞 MagnusBilling TTS IVR Audio Upload API

This API endpoint allows secure upload of MP3 audio files, converts them to Asterisk-compatible WAV format, and assigns them to a specified IVR menu in MagnusBilling. Ideal for dynamic TTS integration and personalized IVR flows.

---

## 🚀 Features

- Secure API key authentication (hashed verification)
- MP3 upload and WAV conversion via FFmpeg
- Automatic file placement in Asterisk sounds directory
- IVR audio assignment via MagnusBilling database
- Dialplan reload for immediate effect
- Error handling and logging for traceability

---

## 📂 Endpoint

**URL:** `/api/tts/index.php`  
**Method:** `POST`  
**Headers:**
```http
Authorization: Bearer <hashed_api_key>
Content-Type: multipart/form-data
```

**Form Data:**
| Field            | Type     | Required | Description                          |
|------------------|----------|----------|--------------------------------------|
| `customer_number`| string   | ✅       | Unique customer identifier           |
| `user_id`        | string   | ✅       | ID of the user performing the upload |
| `ivr_id`         | integer  | ✅       | IVR menu ID to assign audio to       |
| `audio_file`     | file     | ✅       | MP3 file to upload                   |

---

## 🔐 Authentication

This API uses a hashed API key for one-way verification.  
To generate a valid header:
```php
$hashed = password_hash($apiKey, PASSWORD_DEFAULT);
```
Then send:
```http
Authorization: Bearer <hashed>
```

---

## ⚙️ Requirements

- PHP 7.4+
- FFmpeg installed and accessible via CLI
- MagnusBilling with access to `pkg_ivr` table
- Asterisk server with writable `/var/lib/asterisk/sounds/`

---

## 🛠️ Deployment Notes

- Ensure `storage/audio/` is writable by the web server
- Set correct DB credentials in the config section
- Confirm `asterisk` user has permission to access converted files
- Reloading the dialplan applies changes immediately

---

## 📄 License & Copyright

© 2025 Thimira Dilshan  
This code is released under the MIT License. You are free to use, modify, and distribute it with attribution.

---

## 🙌 Author

**Thimira Dilshan**  
Backend Architect | Security-first Developer  
📧 thimirad865@gmail.com

---
