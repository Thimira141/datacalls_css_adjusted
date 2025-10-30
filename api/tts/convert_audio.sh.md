# 🎧 `convert_audio.sh` — MP3 to WAV Conversion Script for Asterisk

**Author:** Thimira Dilhan  
**Purpose:** Converts `.mp3` audio files to Asterisk-compatible `.wav` format and ensures proper file ownership and permissions for playback.

---

## 🛠️ Script Overview

This Bash script performs the following tasks:

1. **Validates input arguments** (expects input `.mp3` and output `.wav` paths)
2. **Converts** the input MP3 file to WAV format using `ffmpeg` with:
   - Sample rate: 8000 Hz
   - Channels: mono
   - Codec: `pcm_s16le` (Asterisk-compatible)
3. **Sets file ownership** to `asterisk:asterisk`
4. **Applies file permissions** (`chmod 644`) for secure playback

---

## Script

```bash
#!/bin/bash

# Usage: ./convert_audio.sh /path/to/input.mp3 /path/to/output.wav

# Check input
if [ -z "$1" ] || [ -z "$2" ]; then
  echo "Usage: $0 /path/to/input.mp3 /path/to/output.wav"
  exit 1
fi

MP3_PATH="$1"
WAV_PATH="$2"

# Convert MP3 to WAV (8000 Hz, mono, PCM)
ffmpeg -y -i "$MP3_PATH" -ar 8000 -ac 1 -c:a pcm_s16le "$WAV_PATH"
if [ $? -ne 0 ]; then
  echo "Audio conversion failed"
  exit 2
fi

# Set file owner
chown asterisk:asterisk "$WAV_PATH"
if [ $? -ne 0 ]; then
  echo "Failed to set file owner"
  exit 3
fi

# Set file permissions
chmod 644 "$WAV_PATH"
if [ $? -ne 0 ]; then
  echo "Failed to set file permissions"
  exit 4
fi

echo "Conversion complete: $WAV_PATH"
```

## 📦 Usage

```bash
./convert_audio.sh /path/to/input.mp3 /path/to/output.wav
```

- **Argument 1:** Full path to the source `.mp3` file
- **Argument 2:** Full path to the desired `.wav` output file

---

## ✅ Example

```bash
./convert_audio.sh /tmp/greeting.mp3 /var/lib/asterisk/sounds/en/greeting.wav
```

---

## 🔐 Permissions

Ensure the script is executable:

```bash
chmod +x /usr/local/bin/convert_audio.sh
```

If called from a PHP script (e.g., via `exec()`), and requires elevated permissions:

- Add the following to `sudo visudo`:

  ```bash
  www-data ALL=(ALL) NOPASSWD: /usr/local/bin/convert_audio.sh
  ```

---

## 🧪 Exit Codes

| Code | Meaning                        |
|------|--------------------------------|
| 1    | Missing input arguments        |
| 2    | Audio conversion failed        |
| 3    | Failed to set file ownership   |
| 4    | Failed to set file permissions |
