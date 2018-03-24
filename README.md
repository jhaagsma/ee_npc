ee_npc
======

Earth Empires Non-Player-Country Project!

*Caveat: I developed this really quickly to get it going, and am now regretting that, as it's very disorganized; I will slowly be refactoring it to make it easier to use.

<br /><br />


TO RUN ON LINUX:


1) Clone the project from github: https://github.com/jhaagsma/ee_npc

2) Copy config_example.php to config.php and fill out your information (see 2.1)

2.1) Go to http://www.earthempires.com/ai/api and Generate an API Key if you don't have one

3) Install php if you don't already have it; on ubuntu sudo apt-get install php 

4) Run ee_npc.php in the terminal: ./ee_npc

5) To Stop it go Control-C

6) Login and see the AI Dev forum for discussion http://www.earthempires.com/forum/ai-development or view the API details at http://www.earthempires.com/api


<br /><br />


TO RUN ON WINDOWS:

1) DOWNLOAD PHP FOR WINDOWS: http://windows.php.net/download/

2) Extract the zip to C:\php

2.1) The name is important! For some reason it didn't like it when i called it PHP5 for example

3) Go to C:\php\ext and COPY php_curl.dll; PASTE it into C:\php

4) OPEN php.ini-development in an editor (notepad)

5) Find the line: ;extension=php_curl.dll

6) Remove the leading semi-colon / Change it to: extension=php_curl.dll

7) Save the changed file as C:\php\php.ini

8) DOWNLOAD the **x86 version** of the MS VC package: http://www.microsoft.com/download/details.aspx?id=30679

9) Install it!

10) Go to http://www.earthempires.com/ai/api and Generate an API Key

11) Download or clone the ee_npc project from github: https://github.com/jhaagsma/ee_npc ( https://github.com/jhaagsma/ee_npc/archive/master.zip )

12) Put the project somewhere like Documents, so you can find it: C:\Users\(your username)\Documents\ee_npc

13) Copy config_example.php to config.php and fill out your information

14) Find ee_npc.php in your explorer, right click on it, and go to Properties, to find the Location that it is at -- in my case it is at C:\Users\qzjul\Documents\ee_npc

15) Open a Windows Terminal

16) (optional) Right click on the top bar / window bar, and click Properties 

17) (optional) Go to the Layout tab, change Screen Buffer width to 200, height to 500; change Window Size width to 200, height to 50.

18) (optional) click OK

19) Run (in the terminal) the following command: C:\php\php.exe "C:\Users\(your username)\Documents\ee_npc\ee_ncp.php" 
(in my case C:\php\php.exe "C:\Users\qzjul\Documents\ee_npc\ee_npc.php" )

20) MAGIC! it starts playing countries!

21) To stop it, go Control-C

22) Login and see the AI Dev forum for discussion http://www.earthempires.com/forum/ai-development or view the API details at http://www.earthempires.com/api


To Contribute!
----

Fork the project, using the fork button.

Then clone it locally, make changes, and push them to github.

When you're happy with them, create a pull request, and I'll merge in your changes after reviewing them. It would be better to make smaller changes at a time, so I can digest them ;-)
