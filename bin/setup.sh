#!/bin/sh
#
# This script should be run any time the repo is set up anew. It establishes
# two git hooks to prevent accidental commits to master.
VER="0.1"

### HELP FUNCTION ###
usage()
{
	echo "usage: setup.sh [ options ]"
	echo "[ -h | --help ] show this message"
}

### OPTIONS ###


# Figure out where we are
BIN=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
GIT="$BIN/../.git"


# Read command-line switches.
SERVERINPUT=false # Whether the user specified the server to sync with.
while [ "$1" != "" ]; do
	case $1 in
		-h | --help )   usage
						exit
						;;
		* )             usage
						exit 1
	esac
	shift
done


# Test for Git hook symlinks
if [ -e "$GIT/hooks/pre-commit" -a -r "$GIT/hooks/pre-commit" ]
then
	echo "Git pre-commit hook already exists."
else
	ln -s "$BIN/.setup/pre-commit" "$GIT/hooks/pre-commit"
	# Check whether that succeeded
	if [ -e "$GIT/hooks/pre-commit" -a -r "$GIT/hooks/pre-commit" ];
	then
		echo "Git pre-commit hook symlinked."
	else
		echo "Error: failed to symlink Git pre-commit hook. Aborting."
		exit 1
	fi
fi

if [ -e "$GIT/hooks/pre-push" -a -r "$GIT/hooks/pre-push" ]
then
	echo "Git pre-push hook already exists."
else
	ln -s "$BIN/.setup/pre-push" "$GIT/hooks/pre-push"
	# Check whether that succeeded
	if [ -e "$GIT/hooks/pre-push" -a -r "$GIT/hooks/pre-push" ];
	then
		echo "Git pre-push hook symlinked."
	else
		echo "Error: failed to symlink Git pre-push hook. Aborting."
		exit
	fi
fi


# Set up PHP CodeSniffer if installed
if [ -e "$BIN/../vendor/bin/phpcs" ]
then
	eval "$BIN/../vendor/bin/phpcs --config-set default_standard PSR12"
fi
