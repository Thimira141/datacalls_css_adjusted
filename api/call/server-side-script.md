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

## Dial-plan script

**Path:** `/etc/asterisk/extensions.conf`  
**Purpose:** script to manage outgoing calls from server

```apache
; =========================
; IVR Playback Context
; =========================
; Added by thimirad865@gmail.com on Y2025/M09/D11 for testing purpose via CLI

[playback-test]
exten => _X.,1,Goto(s,start)                  ; Catch-all: redirect to IVR entry

exten => s,1,Answer()                         ; Answer the call
 same => n,Set(CHANNEL_ID=${CHANNEL})         ; Capture full channel name
 same => n,Wait(1)                             ; Brief pause
 same => n(start),Background(ivr_custom)      ; Play main IVR prompt
 same => n,Set(TIMEOUT(response)=10)          ; Set DTMF timeout
 same => n,Background(beep)                   ; Audible cue
 same => n,Read(DTMF,,1,,3,5)                 ; Capture 1-digit DTMF input
 same => n,System(/usr/bin/php /var/www/html/mbilling/api/call/log-dtmf.php "${CHANNEL_ID}" "${DTMF}") ; Log to backend
 same => n,Goto(${DTMF},1)                    ; Route based on input
 same => n,Hangup()                           ; Fallback hangup

exten => 3,1,Goto(playback-test,s,start)      ; Loop back if caller presses 3

exten => t,1,Playback(vm-goodbye)             ; Timeout handler
 same => n,Hangup()

exten => i,1,Playback(pbx-invalid)            ; Invalid input handler
 same => n,Goto(s,start)

exten => 1,1,Playback(agent-user)             ; Example option
 same => n,Hangup()

exten => 2,1,Playback(auth-thankyou)             ; Another example
 same => n,Hangup()

; =========================
; Entry Point Context
; =========================
; Added by Thimira Dilshan Y2025/M09/D11

[from-user]
; Internal SIP accounts (e.g. Zoiper)
exten => _2XXX,1,NoOp(*** Internal SIP Call ***)
 same => n,Set(CALLERID(num)=${DB(dialplan/cid)})
 same => n,Set(CALLERID(name)=${DB(dialplan/cname)})
 same => n,Dial(SIP/${EXTEN},30,g)            ; Ring SIP account, continue after hangup
 same => n,Goto(playback-test,s,1)            ; Trigger IVR after call ends

; Outbound trunk calls (optional — uncomment to enable)
;exten => _1NXXNXXXXXX,1,NoOp(*** Outbound Call to USA ***)
 ;same => n,Set(CALLERID(num)=${DB(dialplan/cid)})
 ;same => n,Set(CALLERID(name)=${DB(dialplan/cname)})
 ;same => n,Dial(SIP/Telnum/${EXTEN},30,g)
 ;same => n,Goto(playback-test,s,1)

```
