#!/bin/bash

[ `uname -s` != "Darwin" ] && return

function tab () {
    local cdto="$PWD"
    local args="$@"

    if [ -d "$1" ]; then
        cdto=`cd "$1"; pwd`
        args="${@:2}"
    fi

    osascript &>/dev/null <<EOF
        tell application "System Events"
            tell process "Terminal" to keystroke "t" using command down
        end tell
        tell application "Terminal"
            activate
            do script with command "cd \"$cdto\"; $args" in selected tab of the front window
        end tell
EOF
}

tab "cd ~/Documents/agentflix/api && php artisan serve"
tab "cd ~/Documents/agentflix/api && php artisan streams:chat-consume"
tab "cd ~/Documents/agentflix/api && php artisan ai:consume-run-responses"
tab "cd ~/Documents/agentflix/api && php artisan ai:consume-tool-requests"
tab "cd ~/Documents/agentflix/api && php artisan cache:clear && php artisan horizon"
tab "cd ~/Documents/agentflix/app && ng serve"
tab "cd ~/Documents/agentflix/gateway && npm run start"
tab "cd ~/Documents/agentflix && ngrok http 3000"
