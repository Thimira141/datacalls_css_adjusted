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

```apache
[playback-test]
exten => _X.,1,Goto(s,start)

exten => s,1,Answer()
 ;same => n,Set(CALLERID(num)=${DB(dialplan/cid)})       ; Set caller number from AstDB
 same => n,Set(CALLERID(num)=${CID})       ; Set caller number from VARIABLE
 ;same => n,Set(CALLERID(name)=${DB(dialplan/cname)})     ; Set caller name from AstDB
 same => n,Set(CALLERID(name)=${CNAME})     ; Set caller name from VARIABLE
 same => n,Set(CHANNEL_ID=${CHANNEL})                    ; Capture channel name
 same => n,Wait(2)
 same => n,Set(TIMEOUT(response)=10)
 same => n,Read(DTMF,xtd_ivr_custom,1,,3,5)
 same => n,System(/usr/bin/php /var/www/html/mbilling/api/call/log-dtmf.php "${CHANNEL_ID}" "${DTMF}")
 same => n,Goto(${DTMF},1)
 ;same => n(start),Background(xtd_ivr_custom)
 ;same => n,Set(TIMEOUT(response)=10)
 ;same => n,Background(beep)
 ;same => n,Read(DTMF,,1,,3,5)
 ;same => n,System(/usr/bin/php /var/www/html/mbilling/api/call/log-dtmf.php "${CHANNEL_ID}" "${DTMF}")
 ;same => n,Goto(${DTMF},1)
 same => n,Hangup()

exten => 1,1,Playback(xtd_thank-you-for-confirm)
 same => n,Hangup()

exten => 2,1,Verbose(2,User pressed 2 — forwarding to support-bridge)
 same => n,Playback(please-wait)
 ;same => n,ExecIf($["${REDIRECT_DONE}" != "1"]?Goto(support-bridge,s,1)) ; enable if call Loopback or doubleed
 same => n,Goto(support-bridge,s,1) ; disable if call Loopback or doubleed
 same => n,Hangup()

exten => 3,1,Goto(xtd_playback-test,s,start)

exten => t,1,Playback(vm-goodbye)
 same => n,Hangup()

exten => i,1,Playback(pbx-invalid)
 same => n,Goto(s,start)

exten => fallback,1,Playback(vm-goodbye)
 same => n,Hangup()

; =========================
; Customer support bride dial-plan
; =========================
; Added by Thimira Dilshan<thimirad865@gmail.com> Y2025/M10/D14
; Last Updated by Thimira Dilshan<thimirad865@gmail.com> Y2025/M10/D14

[support-bridge]
exten => s,1,NoOp(*** Starting Live Chat Session ***)
 same => n,Set(CHANNEL_ID=${CHANNEL})
 same => n,Verbose(2,Bridging via ${CHANNEL_ID})
 ;same => n,Set(CALLERID(num)=${(dialplan/customer_num)})
 same => n,Set(CALLERID(num)=${CUSTOMER_NUM}) ; SET VALYE BY VARIABLE
 ;same => n,Set(CALLERID(name)=${(dialplan/customer_name)})
 same => n,Set(CALLERID(name)=${CUSTOMER_NAME}) ; SET VALYE BY VARIABLE
 ;same => n,Set(TARGET=${(dialplan/callback_destination)})
 same => n,Set(TARGET=${CALLBACK_DESTINATION}) ; SET VALYE BY VARIABLE
 same => n,GotoIf($["${TARGET}"=""]?fallback,1)

 ; Optional: record the session
 ;same => n,MixMonitor(${CHANNEL_ID}.wav,b)

 ; Optional: play bgm while waiting
 same => n,Playback(music/hold)

 ; Bridge the call (no 'g' so channel dies when remote hangs up)
 ; 'm' for loop music until call pickup
 same => n,Dial(${TARGET},30,m)

 ; Handle post-bridge outcomes
 same => n,GotoIf($["${DIALSTATUS}"="BUSY" | "${DIALSTATUS}"="CHANUNAVAIL" | "${DIALSTATUS}"="CONGESTION"]?fallback,1)
 same => n,Hangup()

exten => fallback,1,Playback(vm-goodbye)
 same => n,Hangup()
```
