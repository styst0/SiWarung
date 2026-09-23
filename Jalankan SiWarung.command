#!/bin/bash
cd "$(dirname "$0")" || exit 1

bash ./start.sh "$@"
kode=$?

printf '\nSelesai. Tekan Enter untuk menutup jendela ini.'
read -r _
exit "$kode"
