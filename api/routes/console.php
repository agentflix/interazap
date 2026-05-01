<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('chat:close-inactive-tickets')->everyFiveMinutes();
