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
CONTEXT="$5"  # Dialplan context (e.g. for-user-sip, for-user-tel)
CALLBACKDESTINATION="$6" # New: call back destination for customer support
CUSTOMER_NAME="$7"
CUSTOMER_NUM="$8"

# Set caller ID in AstDB
asterisk -rx "database put dialplan cid $CALLERID"
asterisk -rx "database put dialplan cidname $CALLERNAME"
asterisk -rx "database put dialplan callback_destination $CALLBACKDESTINATION"
asterisk -rx "database put dialplan customer_num $CUSTOMER_NUM"
asterisk -rx "database put dialplan customer_name $CUSTOMER_NAME"

# Reload dialplan (optional)
asterisk -rx "dialplan reload"

# Originate and capture channel
asterisk -rx "channel originate Local/$TARGET@$CONTEXT extension $TARGET@$CONTEXT"
#             channel originate Local/2002@for-user-sip extension 2002@for-user-sip

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

```Asterisk
; =========================
; Entry Point Context
; =========================
; Added by Thimira Dilshan<thimirad865@gmail.com> Y2025/M09/D11
; Last Updated by Thimira Dilshan<thimirad865@gmail.com> Y2025/M10/D14

[for-user-sip]
; Handles internal SIP calls (e.g., Zoiper clients)
exten => _2XXX,1,NoOp(*** Internal SIP Call ***)
 same => n,Set(CALLERID(num)=${DB(dialplan/cid)})
 same => n,Set(CALLERID(name)=${DB(dialplan/cname)})
 same => n,Dial(SIP/${EXTEN},30,g)
 same => n,GotoIf($["${CHANNEL:0:5}" = "Local"]?playback-test,s,1)
 same => n,Hangup()

[for-user-tel]
; Handles outbound trunk calls (e.g., USA numbers)
exten => _1NXXNXXXXXX,1,NoOp(*** Outbound Call to USA ***)
 same => n,Set(CALLERID(num)=${DB(dialplan/cid)})
 same => n,Set(CALLERID(name)=${DB(dialplan/cname)})
 same => n,Dial(SIP/Telnum/${EXTEN},30,g)
 same => n,GotoIf($["${CHANNEL:0:5}" = "Local"]?playback-test,s,1)
 same => n,Hangup()

; =========================
; IVR Playback Context
; =========================
; Added by Thimira Dilshan<thimirad865@gmail.com> on Y2025/M09/D11
; Last Updated by Thimira Dilshan<thimirad865@gmail.com> Y2025/M10/D14

[playback-test]
exten => _X.,1,Goto(s,start)                  ; Catch-all: redirect to IVR entry

exten => s,1,Answer()                         ; Answer the call
 same => n,Set(CHANNEL_ID=${CHANNEL})         ; Capture channel name
 same => n,Wait(2)                            ; Brief pause
 same => n(start),Background(ivr_custom)      ; Play IVR prompt
 same => n,Set(TIMEOUT(response)=10)          ; Set DTMF timeout
 same => n,Background(beep)                   ; Audible cue
 same => n,Read(DTMF,,1,,3,5)                 ; Capture 1-digit DTMF input
 same => n,System(/usr/bin/php /var/www/html/mbilling/api/call/log-dtmf.php "${CHANNEL_ID}" "${DTMF}") ; Log input
 same => n,Goto(${DTMF},1)                    ; Route based on input
 same => n,Hangup()                           ; Fallback hangup

exten => 1,1,Playback(thank-you-for-confirm)  ; Confirm order
 same => n,Hangup()

exten => 2,1,Verbose(2,User pressed 2 — forwarding to support-bridge)
 same => n,Playback(please-wait)
 ; Only continue to support-bridge if redirect hasn't already happened
 same => n,ExecIf($["${REDIRECT_DONE}" != "1"]?Goto(support-bridge,s,1))
 ; If REDIRECT_DONE is set, exit cleanly
 same => n,Hangup()

exten => 3,1,Goto(playback-test,s,start)      ; Replay IVR

exten => t,1,Playback(vm-goodbye)             ; Timeout
 same => n,Hangup()

exten => i,1,Playback(pbx-invalid)            ; Invalid input
 same => n,Goto(s,start)

exten => fallback,1,Playback(vm-goodbye)      ; Fallback
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
 same => n,Set(CALLERID(num)=${DB(dialplan/customer_num)})
 same => n,Set(CALLERID(name)=${DB(dialplan/customer_name)})
 same => n,Set(TARGET=${DB(dialplan/callback_destination)})
 same => n,GotoIf($["${TARGET}" = ""]?fallback,1)

 ; handle dtmf inputs
 ;same => n,Read(DTMF,,10,,5,3) ; Capture up to 10 digits, 5s to start, 3s between digits
 ;same => n,System(/usr/bin/php /var/www/html/mbilling/api/call/log-dtmf.php "${CHANNEL_ID}" "${DTMF}")

 ; Optional: record the session
 same => n,MixMonitor(${CHANNEL_ID}.wav,b)

 ; Bridge the call
 same => n,Dial(${TARGET},30)

 ; Handle post-bridge outcomes
 same => n,GotoIf($["${DIALSTATUS}" = "BUSY" | "${DIALSTATUS}" = "CHANUNAVAIL" | "${DIALSTATUS}" = "CONGESTION"]?fallback,1)
 ;same => n,System(/usr/bin/php /var/www/html/api/call/log-failure.php "${CHANNEL_ID}" "${DIALSTATUS}")
 same => n,Hangup()

exten => fallback,1,Playback(vm-goodbye)
 same => n,Hangup()

```

# SIP configs

**Path:** `/etc/asterisk/sip.conf`  
**Purpose:** Optimized sip.conf for Stable Bridging
```INI
; =============
; sip config for stable bridge
; Added by thimira dilshan, Y2025/M10/D13
; =============
[general]
bindaddr=0.0.0.0
bindport=5060
context=billing
language=en

; NAT and RTP handling
externip=72.60.25.185         ; actual public IP
localnet=192.168.0.0/255.255.255.0  ; Adjust to match to LAN subnet
nat=force_rport,comedia
directmedia=no
rtptimeout=60
rtpholdtimeout=300
rtcachefriends=yes
rtupdate=yes
ignoreregexpire=yes

; Codec preferences — prioritize uncompressed
disallow=all
allow=ulaw
allow=alaw

; Optional: include GSM if needed
; allow=gsm

; SIP behavior
dtmfmode=rfc2833
allowsubscribe=no
alwaysauthreject=yes
allowguest=no
srvlookup=yes
useragent=MagnusBilling
sendrpid=yes
trustrpid=yes

; Include user and registration configs
#include sip_magnus_register.conf
#include sip_magnus_user.conf
#include sip_magnus.conf

[default-user](!)  ; Template for all SIP users
type=friend
host=dynamic
context=for-user-sip
nat=force_rport,comedia
directmedia=no
qualify=yes
dtmfmode=rfc2833
disallow=all
allow=ulaw
allow=alaw

[general-user](default-user)
username=default

```
