#!/bin/bash
set -e

# Start Apache in foreground
exec apache2ctl -D FOREGROUND
