ee_npc
======

Earth Empires Non-Player-Country Project!



TO RUN ON LINUX:


1) Clone the project from github: https://github.com/jhaagsma/ee_npc

2) Copy config_example.php to config.php and fill out your information

3) Install php if you don't already have it; on ubuntu sudo apt-get install php 

4) Run ee_npc.php in the terminal: ./ee_npc

3) To Stop it go Control-C



TO RUN ON WINDOWS:

1) DOWNLOAD PHP FOR WINDOWS: http://windows.php.net/...12-nts-Win32-VC11-x86.zip

2) Extract the zip to C:\php

2.1) The name is important! For some reason it didn't like it when i called it PHP5 for example

3) Go to C:\php\ext and COPY php_curl.dll; PASTE it into C:\php

4) OPEN php.ini-development in an editor (notepad)

5) Find the line: ;extension=php_curl.dll

6) Remove the leading semi-colon / Change it to: extension=php_curl.dll

7) Save the changed file as C:\php\php.ini

8) DOWNLOAD the **x86 version** of the MS VC package: http://www.microsoft.com/...oad/details.aspx?id=30679

9) Install it!

10) DOWNLOAD the EE Bot I wrote: http://pastebin.com/download.php?i=XEzj4Pe2

11) Open it in an editor (notepad)

12) Go to http://www.earthempires.com/ai/api and Generate an API Key

13) Download or clone the ee_npc project from github: https://github.com/jhaagsma/ee_npc

14) Put the project somewhere like Documents, so you can find it: C:\Users\(your username)\Documents\ee_npc

15) Copy config_example.php to config.php and fill out your information

16) Find ee_npc.php in your explorer, right click on it, and go to Properties, to find the Location that it is at -- in my case it is at C:\Users\qzjul\Documents\ee_npc

17) Open a Windows Terminal

18) (optional) Right click on the top bar / window bar, and click Properties 

19) (optional) Go to the Layout tab, change Screen Buffer width to 200, height to 500; change Window Size width to 200, height to 50.

20) (optional) click OK

21) Run (in the terminal) the following command: C:\php\php.exe "C:\Users\(your username)\Documents\ee_npc\ee_ncp.php" 
(in my case C:\php\php.exe "C:\Users\qzjul\Documents\ee_npc\ee_npc.php" )

22) MAGIC! it starts playing countries!

25) To stop it, go Control-C
