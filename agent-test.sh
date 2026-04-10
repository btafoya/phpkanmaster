#!/bin/bash
# Usage: ./agent-test.sh <base_url> <username> <password> [select_cols]

BASE_URL="${1:-http://tasks.brian.com}"
USERNAME="${2:-btafoya}"
PASSWORD="${3}"
SELECT="${4:-id,title,task_column,notes,priority,due_date,category_id}"

if [[ -z "$PASSWORD" ]]; then
  echo "Usage: $0 <base_url> <username> <password> [select_cols]"
  echo "Example: $0 https://briantafoya.com btafoya mysecret"
  exit 1
fi

echo "=== Getting token for $USERNAME at $BASE_URL ==="
RESPONSE=$(curl -s -X POST "$BASE_URL/api/agent/token" \
  -H "Content-Type: application/json" \
  -d "{\"username\":\"$USERNAME\",\"password\":\"$PASSWORD\"}")

TOKEN=$(echo "$RESPONSE" | jq -r '.token')

if [[ -z "$TOKEN" || "$TOKEN" == "null" ]]; then
  echo "Failed to get token."
  echo "Response: $RESPONSE"
  exit 1
fi

EXPIRES=$(echo "$RESPONSE" | jq -r '.expires_in')
echo "Token obtained. Expires in: ${EXPIRES}s"
echo ""

echo "=== Active Tasks ==="
curl -s "$BASE_URL/api/active_tasks_with_notes?select=$SELECT&order=position.asc" \
  -H "Authorization: Bearer $TOKEN" | jq .

echo ""
echo "=== Task Count ==="
curl -s "$BASE_URL/api/active_tasks_with_notes?select=id" \
  -H "Authorization: Bearer $TOKEN" | jq 'length'
