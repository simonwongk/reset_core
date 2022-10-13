#! /bin/bash

TOTALTX=$(vnstat --oneline | awk -F\; '{ print $10 }')
TOTALTXNUM=$(echo $TOTALTX | awk -F ' ' '{ print $1 }')
TOTALTXUNIT=$(echo $TOTALTX | awk -F ' ' '{ print $2 }')
if [ "x$TOTALTXUNIT" = "xGiB" ] ; then
    IFS='.';
    parts=( $TOTALTXNUM )
    unset IFS;
    verval=$(( 100 * ${parts[0]} + ${parts[1]} ))

    if [ $verval -gt 10000 ] ; then
        aws ses send-email --from geinile@5ea.com --to z@5ea.com --subject 'Warning: Too much bandwidth used' --text "Used $TOTALTX"
    fi
fi