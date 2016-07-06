#!/usr/bin/env bash

# TODO HERE : script to make symlink with the profile allowing quick installation
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR
ln -s ../../../install/profiles/xml/joconde.xml assets/profile/joconde-sans-thesaurus-archives-documentation.xml 