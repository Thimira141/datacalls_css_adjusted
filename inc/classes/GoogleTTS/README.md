# 🗣️ Google Cloud TTS Setup Guide

To enable Google Text-to-Speech (TTS) functionality in this project, follow these steps to securely configure your credentials.

## 🔧 Step-by-Step Instructions

1. **Download Credentials**
   - Go to your [Google Cloud Console](https://console.cloud.google.com/).
   - Navigate to your TTS-enabled project.
   - Create a service account and download the credentials as a `.json` file.

2. **Rename the File**
   - Rename the downloaded file to:
     ```
     google-tts-api-credentials.json
     ```

3. **Place the File**
   - Move the renamed file to the following directory in your project:
     ```
     inc/classes/GoogleTTS/Authentication/
     ```
this program use google tts ssml [Google TTS SSML Ref](https://cloud.google.com/text-to-speech/docs/ssml)

## ⚠️ Security Reminder

Never share your credentials file publicly or commit it to version control. Treat it like a password.
