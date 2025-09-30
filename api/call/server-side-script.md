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

# Set caller ID in AstDB
asterisk -rx "database put dialplan cid $CALLERID"
asterisk -rx "database put dialplan cidname $CALLERNAME"

# Reload dialplan (optional)
asterisk -rx "dialplan reload"

# Originate and capture channel
asterisk -rx "channel originate Local/$TARGET@from-user extension $TARGET@from-user"

# Wait briefly to let the channel start
sleep 1

# Capture the most recent Local channel
CHANNEL=$(asterisk -rx "core show channels concise" | grep "Local/$TARGET@" | head -n 1 | cut -d '!' -f1)
# echo the channel
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
