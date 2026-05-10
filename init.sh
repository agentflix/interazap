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

tab "cd ~/Documents/interazap/api && php artisan serve"
tab "cd ~/Documents/interazap/api && php artisan cache:clear && php artisan horizon"
tab "cd ~/Documents/interazap/app && ng serve"
tab "cd ~/Documents/interazap/gateway && npm run start"
tab "cd ~/Documents/interazap/api && php artisan streams:chat-consume & php artisan ai:consume-run-responses & php artisan ai:consume-tool-requests & wait"
tab "cd ~/Documents/interazap && ngrok http 3000"
