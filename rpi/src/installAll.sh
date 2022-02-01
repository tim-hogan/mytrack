#!/bin/bash
cd install
./install.sh -u
cd ..
rm -r install*
wget https://static.devt.nz/mytrack/rpi/install.zip
unzip install.zip
cd install
chmod +x install.sh
./install.sh
cd ..

