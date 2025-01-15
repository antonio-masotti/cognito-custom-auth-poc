#!/bin/bash

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Function to format JSON log entries
format_log() {
    while IFS= read -r line
    do
        if [[ $line =~ ^\{ ]]; then
            # Parse JSON and format output
            echo $line | jq -r '. | "\(.datetime) [\(.level_name)] \(.message) \(.context // {})"'
        else
            echo "$line"
        fi
    done
}

# Main script
if [ "$1" = "follow" ] || [ "$1" = "-f" ]; then
    echo -e "${GREEN}Following logs...${NC}"
    tail -f var/log/dev.log | format_log
else
    echo -e "${GREEN}Showing last 100 lines...${NC}"
    tail -n 100 var/log/dev.log | format_log
fi
