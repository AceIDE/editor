#!/bin/bash
set -e
set -u

ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=$ACEIDE_SSH_PATH/known_hosts -i $ACEIDE_SSH_PATH/id_rsa $@
