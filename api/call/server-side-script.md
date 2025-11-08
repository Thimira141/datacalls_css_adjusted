# Shell scripts used in API

## 📁 1. Make Call Script

**Path:** `/usr/local/bin/asterisk_call.sh`  
**Purpose:** make call via asterisk and echo the channel

```bash
#!/bin/bash

USERID="$1"
CALLERID="$2"
CALLERNAME="$3"
TARGET="$4"
CONTEXT="$5"  # New: Dialplan context (e.g. for-user-sip, for-user-tel)

# Set caller ID in AstDB
asterisk -rx "database put dialplan cid $CALLERID"
asterisk -rx "database put dialplan cidname $CALLERNAME"

# Reload dialplan (optional)
asterisk -rx "dialplan reload"

# Originate and capture channel
asterisk -rx "channel originate Local/$TARGET@$CONTEXT extension $TARGET@$CONTEXT"

# Wait briefly to let the channel start
sleep 1

# Capture the most recent Local channel
CHANNEL=$(asterisk -rx "core show channels concise" | grep "Local/$TARGET@" | head -n 1 | cut -d '!' -f1)

# Echo the channel
echo "Channel: $CHANNEL"

# added by thimirad865@gmail.com
# (c) 2025 thimira dilshan - 2025-09-15

```

## 📁 2. End Call Script

**Path:** `/usr/local/bin/asterisk_call_end.sh`  
**Purpose:** end call via session id

```bash
#!/bin/bash

EXT="$1"  # e.g. 2002

echo "Scanning for channels related to extension: $EXT"

CHANNELS=$(asterisk -rx "core show channels concise" | grep "$EXT" | cut -d '!' -f1)

if [ -z "$CHANNELS" ]; then
  echo "No active channels found for extension $EXT"
else
  for CH in $CHANNELS; do
    echo "Hanging up: $CH"
    asterisk -rx "channel request hangup $CH"
  done
fi

# added by thimirad865@gmail.com
# (c) 2025 thimira dilshan - 2025-09-18

```

# Dial-plan script

**Path:** `/etc/asterisk/extensions.conf`  
**Purpose:** script to manage outgoing calls from server

```ini
; =========================
; Customer support two leg conditional bridge dial-plan
; =========================
; Added by Thimira Dilshan<thimirad865@gmail.com> Y2025/M11/D08
; Last Updated by Thimira Dilshan<thimirad865@gmail.com> Y2025/M11/D08
[billing]
include => ivr-confirm

[support-wait]
exten => s,1,Answer()
 same => n,Set(__CALL_TAG=${CALL_TAG})
 same => n,Set(CHANNEL_ID=${CHANNEL})
 same => n,NoOp(Call tag is ${CALL_TAG})
 same => n,StartMusicOnHold(support-waiting)
 same => n,NoOp(Support leg is now waiting to be bridged)
 same => n,Wait(3600)
 same => n,StopMusicOnHold()

[ivr-confirm]
exten => s,1,Answer()
 same => n,Set(__CALL_TAG=${CALL_TAG})
 same => n,Set(CHANNEL_ID=${CHANNEL})
 same => n,NoOp(Call tag is ${CALL_TAG})
 same => n,Set(SUPPORT_CHANNEL=${SUPPORT_CHANNEL}) ; passed via Originate
 same => n(start),Read(DTMF,xtd_ivr_custom,1,,3,5)
 same => n,System(/usr/bin/php /var/www/html/mbilling/api/call/log-dtmf.php "${CHANNEL_ID}" "${DTMF}")
 same => n,GotoIf($["${DTMF}"="1"]?playaudio,1)
 same => n,GotoIf($["${DTMF}"="2"]?waitbridge,1)
 same => n,GotoIf($["${DTMF}"="3"]?s,start)
 same => n,Goto(fallback,1)

exten => playaudio,1,Playback(xtd_thank-you-for-confirm)
 same => n,Hangup(${CHANNEL_ID})
 same => n,Hangup(${SUPPORT_CHANNEL})

exten => waitbridge,1,StartMusicOnHold(customer-waiting)
 same => n,Wait(90)
 same => n,StopMusicOnHold()
 same => n,Hangup()

exten => bridge,1,Bridge(${SUPPORT_CHANNEL})
 same => n,NoOp(Bridge completed)
 same => n,Hangup()

exten => fallback,1,Playback(vm-goodbye)
 same => n,Goto(s,start)
 
```
