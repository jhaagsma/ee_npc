ee_npc
======

Earth Empires Non-Player-Country Project!

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/jhaagsma/ee_npc/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jhaagsma/ee_npc/?branch=master)

*Caveat: I developed this really quickly to get it going, and am now regretting that, as it's very disorganized; I will slowly be refactoring it to make it easier to use.

<br /><br />


To Run on Linux
----

1) Clone the project from github: https://github.com/jhaagsma/ee_npc
2) Copy config_example.php to config.php and fill out your information (see 3 & 7)
3) Go to http://www.earthempires.com/ai/api and Generate an API Key if you don't have one
4) Install php if you don't already have it; on ubuntu sudo apt-get install php php-curl
5) Run ee_npc.php in the terminal: ./ee_npc
6) To Stop it go Control-C
7) Login and see the AI Dev forum for discussion http://www.earthempires.com/forum/ai-development or view the API details at http://www.earthempires.com/api


To Run on Windows:
----

1) DOWNLOAD PHP FOR WINDOWS: https://windows.php.net/download/
2) Probably get the newest version, definitely PHP 7+
3) Extract the zip to C:\php
4) The name is important! For some reason it didn't like it when i called it PHP5 for example
5) Go to C:\php\ext and COPY php_curl.dll; PASTE it into C:\php
6) OPEN php.ini-development in an editor (notepad)
7) Find the line: ;extension=php_curl.dll and remove the leading semi-colon / Change it to: extension=php_curl.dll
8) If that line doesn't exist, add it after [curl]
9) Save the changed file as C:\php\php.ini
10 DOWNLOAD the **x86 version** of the MS VC package: http://www.microsoft.com/download/details.aspx?id=30679
11 Install it!
12 Go to http://www.earthempires.com/ai/api and Generate an API Key
13) Download or clone the ee_npc project from github: https://github.com/jhaagsma/ee_npc ( https://github.com/jhaagsma/ee_npc/archive/master.zip )
14) Put the project somewhere like Documents, so you can find it: C:\Users\(your username)\ee_npc
15) Copy config_example.php to config.php and fill out your information
16) PLEASE SEE https://www.earthempires.com/forum/ai-development/instructions-on-how-to-use-the-ai-server-30966?t=1510387005 for further deatils
17) Your AI API key can be acquired from: http://www.earthempires.com/ai/api
18) Find ee_npc.php in your explorer, right click on it, and go to Properties, to find the Location that it is at -- in my case it is at C:\Users\qzjul\Documents\ee_npc
19) Open a Windows Terminal
20) (optional) Right click on the top bar / window bar, and click Properties 
21) (optional) Go to the Layout tab, change Screen Buffer width to 200, height to 500; change Window Size width to 200, height to 50.
22) (optional) click OK
23) Change directory in the terminal to the directory the script is saved in: cd C:\Users\(your username)\ee_npc
24) Run (in the terminal) the following command: C:\php\php.exe ee_ncp.php
(Full terminal line looks, in my case, like: C:\Users\qzjul\ee_npc>C:\php\php.exe ee_npc.php )
25) MAGIC! it starts playing countries!
26) To stop it, go Control-C
27) Login and see the AI Dev forum for discussion http://www.earthempires.com/forum/ai-development or view the API details at http://www.earthempires.com/api


To Contribute!
----

Fork the project, using the fork button.

Then clone it locally, make changes, and push them to github.

When you're happy with them, create a pull request, and I'll merge in your changes after reviewing them. It would be better to make smaller changes at a time, so I can digest them ;-)
