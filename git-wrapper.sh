#!/bin/bash
set -e
set -u

ssh -o StrictHostKeyChecking=yes -o UserKnownHostsFile=$ACEIDE_SSH_PATH/known_hosts -i $ACEIDE_SSH_PATH/id_rsa $@
