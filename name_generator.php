<?php

//NOTE THIS WAS BORROWED FROM http://online-generator.com/ AND CONVERTED TO PHP

/*for($i = 0; $i < 1000; $i++){
	//echo "Guild:\t\t", guild_name(), "\n";
	//echo "Fantasy:\t", fantasy_name(), "\n";
	//echo "WoW:\t\t", wow_name(), "\n";
	//echo "Nick:\t\t", nickname(), "\n";
	//echo "Vampire:\t", vampirename(), "\n";	
	//echo "Pirate:\t\t", pirate_name(), "\n";	
	//echo "Project:\t", projectname(), "\n";	
	//echo "\n---- next round ----\n";
	$name = rand_name();
	if($name === 0)
		echo "\n\noops\n\n";
	echo $name, "\n";
}

exit;
*/

function rand_name(){
	switch(3){ //rand(0,100)%7){
		case 0: return guild_name();
		case 1: return fantasy_name();
		case 2: return wow_name();
		case 3: return nickname();
		case 4: return vampirename();
		case 5: return pirate_name();
		case 6: return projectname();
	}
}

function guild_name(){
	$guild_prefix = array(
		"alliance", "ancestor", "angels", "apotheoses", "beasts", "brothers", "brutes", "children", "conquerors", "creators", "creatures", 
		"daemons", "daimons", "demons", "devils", "dwarfs", "eagles", "falcons", "fathers", "fiends", "fighters", "friends", "guardians", "ghosts", "gods", 
		"heart", "horde", "hordes", "hordes", "knights", "light", "lights", "limbo", "loons", "lords", "maestros", "masses", "masters", "monsters", 
		"ogres", "order", "overlords", "parents", "protector", "protectors", "rebels", "rebels", "renegades", "riders", "riders", "rollers", "runners", 
		"saints", "savages", "slayers", "sinners", "sons", "sphinx", "spirits", "survivors", "trolls", "villains", "warriors", "wolves"
	);
	$guild_name = array(
		"Abardon", "Acaman", "Achard", "Ackmard", "Agon", "Agnar", "Abdun", "Aidan", "Airis", "Aldaren", "Alderman", "Alkirk", "Amerdan", "Anfarc", "Aslan", 
		"Actar", "Atgur", "Atlin", "Aldan", "Badek", "Baduk", "Bedic", "Beeron", "Bein", "Bithon", "Bohl", "Boldel", "Bolrock", "Bredin", "Bredock", "Breen", 
		"tristan", "Bydern", "Cainon", "Calden", "Camon", "Cardon", "Casdon", "Celthric", "Cevelt", "Chamon", "Chidak", "Cibrock", "Cipyar", "Colthan", "Connell", 
		"Cordale", "Cos", "Cyton", "Daburn", "Dawood", "Dak", "Dakamon", "Darkboon", "Dark", "Darg", "Darmor", "Darpick", "Dask", "Deathmar", "Derik", "Dismer", "Dokohan", 
		"Doran", "Dorn", "Dosman", "Draghone", "Drit", "Driz", "Drophar", "Durmark", "Dusaro", "Eckard", "Efar", "Egmardern", "Elvar", "Elmut", "Eli", "Elik", 
		"Elson", "Elthin", "Elbane", "Eldor", "Elidin", "Eloon", "Enro", "Erik", "Erim", "Eritai", "Escariet", "Espardo", "Etar", "Eldar", "Elthen", "Elfdorn", 
		"Etran", "Eythil", "Fearlock", "Fenrirr", "Fildon", "Firdorn", "Florian", "Folmer", "Fronar", "Fydar", "Gai", "Galin", "Galiron", "Gametris", "Gauthus", 
		"Gehardt", "Gemedes", "Gefirr", "Gibolt", "Geth", "Gom", "Gosform", "Gothar", "Gothor", "Greste", "Grim", "Gryni", "Gundir", "Gustov", "Halmar", "Haston", 
		"Hectar", "Hecton", "Helmon", "Hermedes", "Hezaq", "Hildar", "Idon", "Ieli", "Ipdorn", "Ibfist", "Iroldak", "Ixen", "Ixil", "Izic", "Jamik", "Jethol", "Jihb", 
		"Jibar", "Jhin", "Julthor", "Justahl", "Kafar", "Kaldar", "Kelar", "Keran", "Kib", "Kilden", "Kilbas", "Kildar", "Kimdar", "Kilder", "Koldof", "Kylrad", "Lackus", 
		"Lacspor", "Lahorn", "Laracal", "Ledal", "Leith", "Lalfar", "Lerin", "Letor", "Lidorn", "Lich", "Loban", "Lox", "Ludok", "Ladok", "Lupin", "Lurd", "Mardin", 
		"Markard", "Merklin", "Mathar", "Meldin", "Merdon", "Meridan", "Mezo", "Migorn", "Milen", "Mitar", "Modric", "Modum", "Madon", "Mafur", "Mujardin", "Mylo", 
		"Mythik", "Nalfar", "Nadorn", "Naphazw", "Neowald", "Nildale", "Nizel", "Nilex", "Niktohal", "Niro", "Nothar", "Nathon", "Nadale", "Nythil", "Ozhar", "Oceloth", 
		"Odeir", "Ohmar", "Orin", "Oxpar", "Othelen", "Padan", "Palid", "Palpur", "Peitar", "Pendus", "Penduhl", "Pildoor", "Puthor", "Phar", "Phalloz", "Qidan", "Quid", 
		"Qupar", "Randar", "Raydan", "Reaper", "Relboron", "Riandur", "Rikar", "Rismak", "Riss", "Ritic", "Ryodan", "Rysdan", "Rythen", "Rythorn", "Sabalz", "Sadaron", 
		"Safize", "Samon", "Samot", "Secor", "Sedar", "Senic", "Santhil", "Sermak", "Seryth", "Seth", "Shane", "Shard", "Shardo", "Shillen", "Silco", "Sildo", "Silpal", 
		"Sithik", "Soderman", "Sothale", "Staph", "Suktar", "zuth", "Sutlin", "Syr", "Syth", "Sythril", "Talberon", "Telpur", "Temil", "Tamilfist", "Tempist", "Teslanar", 
		"Tespan", "Tesio", "Thiltran", "Tholan", "Tibers", "Tibolt", "Thol", "Tildor", "Tilthan", "Tobaz", "Todal", "Tothale", "Touck", "Tok", "Tuscan", "Tusdar", "Tyden", 
		"Uerthe", "Uhmar", "Uhrd", "Updar", "Uther", "Vacon", "Valker", "Valyn", "Vectomon", "Veldar", "Velpar", "Vethelot", "Vildher", "Vigoth", "Vilan", "Vildar", "Vi", 
		"Vinkol", "Virdo", "Voltain", "Wanar", "Wekmar", "Weshin", "Witfar", "Wrathran", "Waytel", "Wathmon", "Wider", "Wyeth", "Xandar", "Xavor", "Xenil", "Xelx", "Xithyl", 
		"Yerpal", "Yesirn", "Ylzik", "Zak", "Zek", "Zerin", "Zestor", "Zidar", "Zigmal", "Zilex", "Zilz", "Zio", "Zotar", "Zutar", "Zytan", "Amlen", "Atmas", "Balbaar", 
		"Bazol", "Bazyl", "Bealx", "Belep", "Bernin", "Bernout", "Bulxso", "Byakuya", "Calebaas", "Chaoshof", "Carelene", "Daigorn", "Darkonn", "Davezzorr", "Deltacus", 
		"Diaboltz", "Dommekoe", "Donatel", "Druppel", "Elpenor", "Eriz", "Exz", "Falcord", "Fayenia", "Fhuyr", "Fibroe", "Grenjar", "Haiduc", "Holypetra", "Hubok", 
		"Ihaspusi", "Ijin", "Irmeli", "Ixtli", "Jäger", "Jelli", "Jihnbo", "Jihnj", "rambol", "Johno", "Kambui", "Karmak", "Kastenz", "Kdenseje", "Kiarani", "Latzaf", 
		"Leeuwin", "Leurke", "Lolimgolas", "Looladin", "Lya", "Maevi", "Matsa", "Minox", "Mjoed", "Nomagestus", "Mutaro", "Narrayah", "Naterish", "Nothrad", "Okuni", 
		"Omgicrit", "Onimia", "Pingala", "Pluitti", "Print", "Pronyma", "Psyra", "Purhara", "Qtis", "Rahe", "Realkoyo", "Saljin", "Slogum", "Sojiro", "Spirgel", "Staafsak", 
		"Sucz", "Tiamath", "Tybell", "Valtaur", "Veulix", "Warmage", "Wortel", "Wroogny", "Yakkity", "Yakkityyak", "Yina", "Zhrug", "Xandread"
	);
	$guild_female = array(
		"Acele", "Acholate", "Ada", "Adiannon", "Adorra", "Ahanna", "Akara", "Akassa", "Akia", "Amara", "Amarisa", "Amarizi", "Ana", "Andonna", "Ariannona", "Arina", 
		"Arryn", "Asada", "Awnia", "Ayne", "Basete", "Bathelie", "Bethel", "Brana", "Brynhilde", "Calene", "Calina", "Celestine", "Corda", "Enaldie", "Enoka", "Enoona", 
		"Errinaya", "Fayne", "Frederika", "Frida", "Gvene", "Gwethana", "Helenia", "Hildandi", "Helvetica", "Idona", "Irina", "Irene", "Illia", "Irona", "Justalyne", 
		"Kassina", "Kilia", "Kressara", "Laela", "Laenaya", "Lelani", "Luna", "Linyah", "Lyna", "Lynessa", "Mehande", "Melisande", "Midiga", "Mirayam", "Mylene", 
		"Naria", "Narisa", "Nelena", "Nimaya", "Nymia", "Ochala", "Olivia", "Onathe", "Parthinia", "Philadona", "Prisane", "Rhyna", "Rivatha", "Ryiah", "Sanata", "Sathe", 
		"Senira", "Sennetta", "Serane", "Sevestra", "Sidara", "Sidathe", "Sina", "Sunete", "Synestra", "Sythini", "zena", "Tabithi", "Tomara", "Teressa", "Tonica", 
		"Thea", "Teressa", "Urda", "Usara", "Useli", "Unessa", "ursula", "Venessa", "Wanera", "Wellisa", "yeta", "Ysane", "Yve", "Yviene", "Zana", "Zathe", "Zecele", 
		"Zenobe", "Zema", "Zestia", "Zilka", "Zoucka", "Zona", "Zyneste", "Zynoa"
	);
	$prefix = $guild_prefix;
	$suffix = $guild_name;
	$suffix_female = $guild_female;
	$n1 = rand(0,count($prefix)-1); //parseInt(Math.random() * $prefix.length);
	$n2 = rand(0,count($suffix)-1); //parseInt(Math.random() * $suffix.length);
	$n2ekstra = rand(0,count($suffix_female)-1); //parseInt(Math.random() * $suffix_female.length);
	$prename = ucfirst($prefix[$n1]); //prefix[n1].slice(0, 1).toUpperCase() . $prefix[n1].slice(1);
	$sufname = ucfirst($suffix[$n2]); //suffix[n2].slice(0, 1).toUpperCase() . $suffix[n2].slice(1);
	$fem_sufname = ucfirst($suffix_female[$n2ekstra]); //suffix_female[n2ekstra].slice(0, 1).toUpperCase() . $suffix_female[n2ekstra].slice(1);
	$n3 = rand(0,100); //rand(0,100);
	if($n3 <= 18){
		$name = $prename . " of " . $sufname;
	}elseif($n3 > 18 && $n3 <= 21){
		$name = $prename . " out of " . $sufname;
	}elseif($n3 > 21 && $n3 <= 41){
		$name = "The " . $prename . " of " . $sufname;
	}elseif($n3 > 41 && $n3 <= 49){
		$name = "The " . $prename . " from " . $sufname;
	}elseif($n3 > 49 && $n3 <= 57){
		$name = $prename . " from " . $sufname;
	}elseif($n3 > 57 && $n3 <= 61){
		$name = $sufname . " " . $prename;
	}elseif($n3 > 61 && $n3 <= 70){
		$name = $sufname . " " . $prename;
	}elseif($n3 > 70 && $n3 <= 90){
		$name = $sufname . "s " . $prename;
	}elseif($n3 > 90 && $n3 <= 95){
		$name = $sufname . " of the " . $prename;
	}elseif($n3 > 95 && $n3 <= 97){
		$name = $sufname . " beneath the " . $prename;
	}elseif($n3 > 97 && $n3 <= 100){
		$name = $sufname . " above the " . $prename;
	}
	
	return $name;
}

function fantasy_name(){
	$fantasy_male = array(
		"Abardon", "Acaman", "Achard", "Ackmard", "Agon", "Agnar", "Abdun", "Aidan", "Airis", "Aldaren", "Alderman", "Alkirk", "Amerdan", "Anfarc", "Aslan", 
		"Actar", "Atgur", "Atlin", "Aldan", "Badek", "Baduk", "Bedic", "Beeron", "Bein", "Bithon", "Bohl", "Boldel", "Bolrock", "Bredin", "Bredock", "Breen", 
		"tristan", "Bydern", "Cainon", "Calden", "Camon", "Cardon", "Casdon", "Celthric", "Cevelt", "Chamon", "Chidak", "Cibrock", "Cipyar", "Colthan", "Connell", 
		"Cordale", "Cos", "Cyton", "Daburn", "Dawood", "Dak", "Dakamon", "Darkboon", "Dark", "Darg", "Darmor", "Darpick", "Dask", "Deathmar", "Derik", "Dismer", 
		"Dokohan", "Doran", "Dorn", "Dosman", "Draghone", "Drit", "Driz", "Drophar", "Durmark", "Dusaro", "Eckard", "Efar", "Egmardern", "Elvar", "Elmut", "Eli", 
		"Elik", "Elson", "Elthin", "Elbane", "Eldor", "Elidin", "Eloon", "Enro", "Erik", "Erim", "Eritai", "Escariet", "Espardo", "Etar", "Eldar", "Elthen", "Etran", 
		"Eythil", "Fearlock", "Fenrirr", "Fildon", "Firdorn", "Florian", "Folmer", "Fronar", "Fydar", "Gai", "Galin", "Galiron", "Gametris", "Gauthus", "Gehardt", 
		"Gemedes", "Gefirr", "Gibolt", "Geth", "Gom", "Gosform", "Gothar", "Gothor", "Greste", "Grim", "Gryni", "Gundir", "Gustov", "Halmar", "Haston", "Hectar", 
		"Hecton", "Helmon", "Hermedes", "Hezaq", "Hildar", "Idon", "Ieli", "Ipdorn", "Ibfist", "Iroldak", "Ixen", "Ixil", "Izic", "Jamik", "Jethol", "Jihb", "Jibar", 
		"Jhin", "Julthor", "Justahl", "Kafar", "Kaldar", "Kelar", "Keran", "Kib", "Kilden", "Kilbas", "Kildar", "Kimdar", "Kilder", "Koldof", "Kylrad", "Lackus", 
		"Lacspor", "Lahorn", "Laracal", "Ledal", "Leith", "Lalfar", "Lerin", "Letor", "Lidorn", "Lich", "Loban", "Lox", "Ludok", "Ladok", "Lupin", "Lurd", "Mardin", 
		"Markard", "Merklin", "Mathar", "Meldin", "Merdon", "Meridan", "Mezo", "Migorn", "Milen", "Mitar", "Modric", "Modum", "Madon", "Mafur", "Mujardin", "Mylo", 
		"Mythik", "Nalfar", "Nadorn", "Naphazw", "Neowald", "Nildale", "Nizel", "Nilex", "Niktohal", "Niro", "Nothar", "Nathon", "Nadale", "Nythil", "Ozhar", "Oceloth", 
		"Odeir", "Ohmar", "Orin", "Oxpar", "Othelen", "Padan", "Palid", "Palpur", "Peitar", "Pendus", "Penduhl", "Pildoor", "Puthor", "Phar", "Phalloz", "Qidan", 
		"Quid", "Qupar", "Randar", "Raydan", "Reaper", "Relboron", "Riandur", "Rikar", "Rismak", "Riss", "Ritic", "Ryodan", "Rysdan", "Rythen", "Rythorn", "Sabalz", 
		"Sadaron", "Safize", "Samon", "Samot", "Secor", "Sedar", "Senic", "Santhil", "Sermak", "Seryth", "Seth", "Shane", "Shard", "Shardo", "Shillen", "Silco", "Sildo", 
		"Silpal", "Sithik", "Soderman", "Sothale", "Staph", "Suktar", "zuth", "Sutlin", "Syr", "Syth", "Sythril", "Talberon", "Telpur", "Temil", "Tamilfist", "Tempist", 
		"Teslanar", "Tespan", "Tesio", "Thiltran", "Tholan", "Tibers", "Tibolt", "Thol", "Tildor", "Tilthan", "Tobaz", "Todal", "Tothale", "Touck", "Tok", "Tuscan", "Tusdar", 
		"Tyden", "Uerthe", "Uhmar", "Uhrd", "Updar", "Uther", "Vacon", "Valker", "Valyn", "Vectomon", "Veldar", "Velpar", "Vethelot", "Vildher", "Vigoth", "Vilan", "Vildar", 
		"Vi", "Vinkol", "Virdo", "Voltain", "Wanar", "Wekmar", "Weshin", "Witfar", "Wrathran", "Waytel", "Wathmon", "Wider", "Wyeth", "Xandar", "Xavor", "Xenil", "Xelx", 
		"Xithyl", "Yerpal", "Yesirn", "Ylzik", "Zak", "Zek", "Zerin", "Zestor", "Zidar", "Zigmal", "Zilex", "Zilz", "Zio", "Zotar", "Zutar", "Zytan"
	);
	$fantasy_female = array(
		"Acele Acholate", "Ada", "Adiannon", "Adorra", "Ahanna", "Akara", "Akassa", "Akia", "Amara", "Amarisa", "Amarizi", "Ana", "Andonna", "Ariannona", "Arina", "Arryn", 
		"Asada", "Awnia", "Ayne", "Basete", "Bathelie", "Bethel", "Brana", "Brynhilde", "Calene", "Calina", "Celestine", "Corda", "Enaldie", "Enoka", "Enoona", "Errinaya", 
		"Fayne", "Frederika", "Frida", "Gvene", "Gwethana", "Helenia", "Hildandi", "Helvetica", "Idona", "Irina", "Irene", "Illia", "Irona", "Justalyne", "Kassina", "Kilia", 
		"Kressara", "Laela", "Laenaya", "Lelani", "Luna", "Linyah", "Lyna", "Lynessa", "Mehande", "Melisande", "Midiga", "Mirayam", "Mylene", "Naria", "Narisa", "Nelena", 
		"Nimaya", "Nymia", "Ochala", "Olivia", "Onathe", "Parthinia", "Philadona", "Prisane", "Rhyna", "Rivatha", "Ryiah", "Sanata", "Sathe", "Senira", "Sennetta", "Serane", 
		"Sevestra", "Sidara", "Sidathe", "Sina", "Sunete", "Synestra", "Sythini", "zena", "Tabithi", "Tomara", "Teressa", "Tonica", "Thea", "Teressa", "Urda", "Usara", "Useli", 
		"Unessa", "ursula", "Venessa", "Wanera", "Wellisa", "yeta", "Ysane", "Yve", "Yviene", "Zana", "Zathe", "Zecele", "Zenobe", "Zema", "Zestia", "Zilka", "Zoucka", "Zona", 
		"Zyneste", "Zynoa"
	);
	$fantasy_surname = array(
		"Abardon", "Acaman", "Achard", "Ackmard", "Agon", "Agnar", "Aldan", "Abdun", "Aidan", "Airis", "Aldaren", "Alderman", "Alkirk", "Amerdan", "Anfarc", "Aslan", "Actar", 
		"Atgur", "Atlin", "Badek", "Baduk", "Bedic", "Beeron", "Bein", "Bithon", "Bohl", "Boldel", "Bolrock", "Bredin", "Bredock", "Breen", "tristan", "Bydern", "Cainon", 
		"Calden", "Camon", "Cardon", "Casdon", "Celthric", "Cevelt", "Chamon", "Chidak", "Cibrock", "Cipyar", "Colthan", "Connell", "Cordale", "Cos", "Cyton", "Daburn", "Dawood", 
		"Dak", "Dakamon", "Darkboon", "Dark", "Darmor", "Darpick", "Dask", "Deathmar", "Derik", "Dismer", "Dokohan", "Doran", "Dorn", "Dosman", "Draghone", "Drit", "Driz", 
		"Drophar", "Durmark", "Dusaro", "Eckard", "Efar", "Egmardern", "Elvar", "Elmut", "Eli", "Elik", "Elson", "Elthin", "Elbane", "Eldor", "Elidin", "Eloon", "Enro", "Erik", 
		"Erim", "Eritai", "Escariet", "Espardo", "Etar", "Eldar", "Elthen", "Etran", "Eythil", "Fearlock", "Fenrirr", "Fildon", "Firdorn", "Florian", "Folmer", "Fronar", 
		"Fydar", "Gai", "Galin", "Galiron", "Gametris", "Gauthus", "Gehardt", "Gemedes", "Gefirr", "Gibolt", "Geth", "Gom", "Gosform", "Gothar", "Gothor", "Greste", "Grim", 
		"Gryni", "Gundir", "Gustov", "Halmar", "Haston", "Hectar", "Hecton", "Helmon", "Hermedes", "Hezaq", "Hildar", "Idon", "Ieli", "Ipdorn", "Ibfist", "Iroldak", "Ixen", 
		"Ixil", "Izic", "Jamik", "Jethol", "Jihb", "Jibar", "Jhin", "Julthor", "Justahl", "Kafar", "Kaldar", "Kelar", "Keran", "Kib", "Kilden", "Kilbas", "Kildar", "Kimdar", 
		"Kilder", "Koldof", "Kylrad", "Lackus", "Lacspor", "Lahorn", "Laracal", "Ledal", "Leith", "Lalfar", "Lerin", "Letor", "Lidorn", "Lich", "Loban", "Lox", "Ludok", "Ladok", 
		"Lupin", "Lurd", "Mardin", "Markard", "Merklin", "Mathar", "Meldin", "Merdon", "Meridan", "Mezo", "Migorn", "Milen", "Mitar", "Modric", "Modum", "Madon", "Mafur", 
		"Mujardin", "Mylo", "Mythik", "Nalfar", "Nadorn", "Naphazw", "Neowald", "Nildale", "Nizel", "Nilex", "Niktohal", "Niro", "Nothar", "Nathon", "Nadale", "Nythil", 
		"Ozhar", "Oceloth", "Odeir", "Ohmar", "Orin", "Oxpar", "Othelen", "Padan", "Palid", "Palpur", "Peitar", "Pendus", "Penduhl", "Pildoor", "Puthor", "Phar", "Phalloz", 
		"Qidan", "Quid", "Qupar", "Randar", "Raydan", "Reaper", "Relboron", "Riandur", "Rikar", "Rismak", "Riss", "Ritic", "Ryodan", "Rysdan", "Rythen", "Rythorn", "Sabalz", 
		"Sadaron", "Safize", "Samon", "Samot", "Secor", "Sedar", "Senic", "Santhil", "Sermak", "Seryth", "Seth", "Shane", "Shard", "Shardo", "Shillen", "Silco", "Sildo", 
		"Silpal", "Sithik", "Soderman", "Sothale", "Staph", "Suktar", "zuth", "Sutlin", "Syr", "Syth", "Sythril", "Talberon", "Telpur", "Temil", "Tamilfist", "Tempist", 
		"Teslanar", "Tespan", "Tesio", "Thiltran", "Tholan", "Tibers", "Tibolt", "Thol", "Tildor", "Tilthan", "Tobaz", "Todal", "Tothale", "Touck", "Tok", "Tuscan", "Tusdar", 
		"Tyden", "Uerthe", "Uhmar", "Uhrd", "Updar", "Uther", "Vacon", "Valker", "Valyn", "Vectomon", "Veldar", "Velpar", "Vethelot", "Vildher", "Vigoth", "Vilan", "Vildar", 
		"Vi", "Vinkol", "Virdo", "Voltain", "Wanar", "Wekmar", "Weshin", "Witfar", "Wrathran", "Waytel", "Wathmon", "Wider", "Wyeth", "Xandar", "Xavor", "Xenil", "Xelx", 
		"Xithyl", "Yerpal", "Yesirn", "Ylzik", "Zak", "Zek", "Zerin", "Zestor", "Zidar", "Zigmal", "Zilex", "Zilz", "Zio", "Zotar", "Zutar", "Zytan"
	);
	$prefix_male = $fantasy_male;
	$prefix_female = $fantasy_female;
	$suffix = $fantasy_surname;
	$n1m = rand(0, count($prefix_male)-1); //parseInt(Math.random() * $prefix_male.length);
	$n1f = rand(0, count($prefix_female)-1); //parseInt(Math.random() * $prefix_female.length);
	$n2 = rand(0, count($suffix)-1); //parseInt(Math.random() * $suffix.length);
	$n2ekstra = rand(0, count($suffix)-1); //parseInt(Math.random() * $suffix.length);
	//$extraname = "extranahme";
	$prename_male = ucfirst($prefix_male[$n1m]); //.slice(0, 1).toUpperCase() . $prefix_male[n1m].slice(1);
	$prename_female = ucfirst($prefix_female[$n1f]); //.slice(0, 1).toUpperCase() . $prefix_female[n1f].slice(1);
	$sufname = ucfirst($suffix[$n2]); //.slice(0, 1).toUpperCase() . $suffix[n2].slice(1);
	$extraname = ucfirst($suffix[$n2ekstra]); //.slice(0, 1).toUpperCase() . $suffix[n2ekstra].slice(1);
	$n3 = rand(0,100);
	if($n3 <= 12){
		$name = $prename_male;
	}elseif($n3 > 12 && $n3 <= 20){
		$name = $prename_female;
	}elseif($n3 > 20 && $n3 <= 50){
		$name = $prename_male . " " . $sufname;
	}elseif($n3 > 50 && $n3 <= 70){
		$name = $prename_female . " " . $sufname;
	}elseif($n3 > 70 && $n3 <= 85){
		$name = $prename_male . " " . $extraname . " " . $sufname;
	}elseif($n3 > 85){
		$name = $prename_female . " " . $extraname . " " . $sufname;
	}
	return $name;
}

function wow_name(){
	$wow_male = array(
		"Abardon", "Acaman", "Achard", "Ackmard", "Agon", "Agnar", "Abdun", "Aidan", "Airis", "Aldaren", "Alderman", "Alkirk", "Amerdan", "Anfarc", "Aslan", "Actar", 
		"Atgur", "Atlin", "Aldan", "Badek", "Baduk", "Bedic", "Beeron", "Bein", "Bithon", "Bohl", "Boldel", "Bolrock", "Bredin", "Bredock", "Breen", "tristan", "Bydern", 
		"Cainon", "Calden", "Camon", "Cardon", "Casdon", "Celthric", "Cevelt", "Chamon", "Chidak", "Cibrock", "Cipyar", "Colthan", "Connell", "Cordale", "Cos", "Cyton", 
		"Daburn", "Dawood", "Dak", "Dakamon", "Darkboon", "Dark", "Darg", "Darmor", "Darpick", "Dask", "Deathmar", "Derik", "Dismer", "Dokohan", "Doran", "Dorn", "Dosman", 
		"Draghone", "Drit", "Driz", "Drophar", "Durmark", "Dusaro", "Eckard", "Efar", "Egmardern", "Elvar", "Elmut", "Eli", "Elik", "Elson", "Elthin", "Elbane", "Eldor", 
		"Elidin", "Eloon", "Enro", "Erik", "Erim", "Eritai", "Escariet", "Espardo", "Etar", "Eldar", "Elthen", "Elfdorn", "Etran", "Eythil", "Fearlock", "Fenrir", "Fildon", 
		"Firdorn", "Florian", "Folmer", "Fronar", "Fydar", "Gai", "Galin", "Galiron", "Gametris", "Gauthus", "Gehardt", "Gemedes", "Gefir", "Gibolt", "Geth", "Gom", 
		"Gosform", "Gothar", "Gothor", "Greste", "Grim", "Gryni", "Gundir", "Gustov", "Halmar", "Haston", "Hectar", "Hecton", "Helmon", "Hermedes", "Hezaq", "Hildar", 
		"Idon", "Ieli", "Ipdorn", "Ibfist", "Iroldak", "Ixen", "Ixil", "Izic", "Jamik", "Jethol", "Jihb", "Jibar", "Jhin", "Julthor", "Justahl", "Kafar", "Kaldar", "Kelar", 
		"Keran", "Kib", "Kilden", "Kilbas", "Kildar", "Kimdar", "Kilder", "Koldof", "Kylrad", "Lackus", "Lacspor", "Lahorn", "Laracal", "Ledal", "Leith", "Lalfar", "Lerin", 
		"Letor", "Lidorn", "Lich", "Loban", "Lox", "Ludok", "Ladok", "Lupin", "Lurd", "Mardin", "Markard", "Merklin", "Mathar", "Meldin", "Merdon", "Meridan", "Mezo", "Migorn", 
		"Milen", "Mitar", "Modric", "Modum", "Madon", "Mafur", "Mujardin", "Mylo", "Mythik", "Nalfar", "Nadorn", "Naphazw", "Neowald", "Nildale", "Nizel", "Nilex", "Niktohal", 
		"Niro", "Nothar", "Nathon", "Nadale", "Nythil", "Ozhar", "Oceloth", "Odeir", "Ohmar", "Orin", "Oxpar", "Othelen", "Padan", "Palid", "Palpur", "Peitar", "Pendus", 
		"Penduhl", "Pildoor", "Puthor", "Phar", "Phalloz", "Qidan", "Quid", "Qupar", "Randar", "Raydan", "Reaper", "Relboron", "Riandur", "Rikar", "Rismak", "Riss", "Ritic", 
		"Ryodan", "Rysdan", "Rythen", "Rythorn", "Sabalz", "Sadaron", "Safize", "Samon", "Samot", "Secor", "Sedar", "Senic", "Santhil", "Sermak", "Seryth", "Seth", "Shane", 
		"Shard", "Shardo", "Shillen", "Silco", "Sildo", "Silpal", "Sithik", "Soderman", "Sothale", "Staph", "Suktar", "zuth", "Sutlin", "Syr", "Syth", "Sythril", "Talberon", 
		"Telpur", "Temil", "Tamilfist", "Tempist", "Teslanar", "Tespan", "Tesio", "Thiltran", "Tholan", "Tibers", "Tibolt", "Thol", "Tildor", "Tilthan", "Tobaz", "Todal", 
		"Tothale", "Touck", "Tok", "Tuscan", "Tusdar", "Tyden", "Uerthe", "Uhmar", "Uhrd", "Updar", "Uther", "Vacon", "Valker", "Valyn", "Vectomon", "Veldar", "Velpar", 
		"Vethelot", "Vildher", "Vigoth", "Vilan", "Vildar", "Vi", "Vinkol", "Virdo", "Voltain", "Wanar", "Wekmar", "Weshin", "Witfar", "Wrathran", "Waytel", "Wathmon", 
		"Wider", "Wyeth", "Xandar", "Xavor", "Xenil", "Xelx", "Xithyl", "Yerpal", "Yesirn", "Ylzik", "Zak", "Zek", "Zerin", "Zestor", "Zidar", "Zigmal", "Zilex", "Zilz", 
		"Zio", "Zotar", "Zutar", "Zytan", "Amlen", "Atmas", "Balbaar", "Bazol", "Bazyl", "", "Bealx", "", "Belep", "Bernin", "Bernout", "Bulxso", "Byakuya", "Calebaas", 
		"Chaoshof", "Carelene", "Daigorn", "Darkonn", "Davezzorr", "Deltacus", "Diaboltz", "Dommekoe", "Donatel", "Druppel", "Elpenor", "Eriz", "Exz", "Falcord", "Fayenia", 
		"Fhuyr", "Fibroe", "Grenjar", "Haiduc", "Holypetra", "Hubok", "Ihaspusi", "Ijin", "Irmeli", "Ixtli", "Jager", "Jelli", "Jihnbo", "Jihnj", "rambol", "Johno", "", 
		"Kambui", "Karmak", "Kastenz", "Kdenseje", "Kiarani", "Latzaf", "Leeuwin", "Leurke", "Lolimgolas", "Looladin", "Lya", "Maevi", "Matsa", "Minox", "Mjoed", "Nomagestus", 
		"Mutaro", "Narrayah", "Naterish", "Nothrad", "Okuni", "Omgicrit", "Onimia", "Pingala", "Pluitti", "Print", "Pronyma", "Psyra", "Purhara", "Qtis", "Rahe", "Realkoyo", 
		"Saljin", "Slogum", "Sojiro", "Spirgel", "Staafsak", "Sucz", "Tiamath", "Tybell", "Valtaur", "Veulix", "Warmage", "Wortel", "Wroogny", "Yakkity", "Yakkityyak", "Yina", 
		"Zhrug", "Xandread"
	);
	$wow_female = array(
		"Acele", "Acholate", "Ada", "Adiannon", "Adorra", "Ahanna", "Akara", "Akassa", "Akia", "Amara", "Amarisa", "Amarizi", "Ana", "Andonna", "Ariannona", "Arina", "Arryn", 
		"Asada", "Awnia", "Ayne", "Basete", "Bathelie", "Bethel", "Brana", "Brynhilde", "Calene", "Calina", "Celestine", "Corda", "Enaldie", "Enoka", "Enoona", "Errinaya", "Fayne", 
		"Frodaka", "Frida", "Gvene", "Gwethana", "Helenia", "Hildandi", "Helvetica", "Idona", "Irina", "Irene", "Illia", "Irona", "Astalyne", "Kassina", "Kilia", "Kressara", "Laela", 
		"Laenaya", "Lelani", "Luna", "Linyah", "Lyna", "Lynessa", "Mehande", "Melisande", "Midiga", "Mirayam", "Mylene", "Naria", "Narisa", "Nelena", "Nimaya", "Nymia", "Ochala", 
		"Olivia", "Onathe", "Parthinia", "Philadona", "Prisane", "Rhyna", "Rivatha", "Ryiah", "Sanata", "Sathe", "Senira", "Sennetta", "Serane", "Sevestra", "Sidara", "Sidathe", 
		"Sina", "Sunete", "Synestra", "Sythini", "zena", "Tabithi", "Tomara", "Teressa", "Tonica", "Thea", "Teressa", "Urda", "Usara", "Useli", "Unessa", "ursula", "Venessa", "Wanera", 
		"Wellisa", "yeta", "Ysane", "Yve", "Yviene", "Zana", "Zathe", "Zecele", "Zenobe", "Zema", "Zestia", "Zilka", "Zoucka", "Zona", "Zyneste", "Zynoa"
	);
	$wow_surname = array(
		"Abardon", "Acaman", "Achard", "Ackmard", "Agon", "Agnar", "Aldan", "Abdun", "Aidan", "Airis", "Aldaren", "Alderman", "Alkirk", "Amerdan", "Anfarc", "Aslan", "Actar", 
		"Atgur", "Atlin", "Badek", "Baduk", "Bedic", "Beeron", "Bein", "Bithon", "Bohl", "Boldel", "Bolrock", "Bredin", "Bredock", "Breen", "tristan", "Bydern", "Cainon", 
		"Calden", "Camon", "Cardon", "Casdon", "Celthric", "Cevelt", "Chamon", "Chidak", "Cibrock", "Cipyar", "Colthan", "Connel", "Cordal", "Cos", "Cyton", "Daburn", "Dawod", 
		"Dak", "Dakmon", "Dakboon", "Dark", "Dag", "Darmor", "Darpick", "Dask", "Deatmar", "Derik", "Dismer", "Dokohan", "Doran", "Dorn", "Dosk", "Drag", "Drit", "Driz", "Drophar", 
		"Durmark", "Dusaro", "Eckard", "Efar", "Egmarg", "Elvar", "Elmut", "Eli", "Elik", "Elson", "Elthin", "Elbane", "Eldor", "Elidin", "Eloon", "Enro", "Erak", "Erim", "Ezit", 
		"Escar", "Espard", "Etar", "Eldar", "Elthen", "Etran", "Eytil", "Farlok", "Fenrir", "Fildon", "Firdorn", "Florian", "Folmer", "Fronar", "Fydar", "Gai", "Galin", "Galiron", 
		"Gametris", "Gaut", "Gelan", "Gamud", "Gefirr", "Gibolt", "Geth", "Gom", "Gosform", "Gothar", "Gothor", "Greste", "Grim", "Gryni", "Gundir", "Gustov", "Halmar", "Haston", 
		"Hectar", "Hecton", "Helmon", "Hades", "Hezaq", "Hildar", "Idon", "Ieli", "Ipdorn", "Ibfist", "Iroldak", "Ixen", "Ixil", "Izic", "Jamik", "Jethol", "Jihb", "Jibar", "Jhin", 
		"Julthor", "Justahl", "Kafar", "Kaldar", "Kelar", "Keran", "Kib", "Kilden", "Kilbas", "Kildar", "Kimdar", "Kilder", "Koldof", "Kylrad", "Lackus", "Laspor", "Lahorn", "Larcal", 
		"Ledal", "Leith", "Lalfar", "Lerin", "Letor", "Lidorn", "Lich", "Loban", "Lox", "Ludok", "Ladok", "Lupin", "Lurd", "Mardin", "Markard", "Merklan", "Mathar", "Meldin", "Merdon", 
		"Meridan", "Mezo", "Migorn", "Milen", "Mitar", "Modric", "Modum", "Madon", "Mafur", "Murdin", "Mylo", "Mythik", "Nalfar", "Nadorn", "Naphazw", "Neowald", "Nildale", "Nizel", 
		"Nilex", "Niktal", "Niro", "Nothar", "Nathon", "Nadale", "Nythil", "Ozhar", "Ozeloth", "Odeir", "Ohmar", "Orin", "Oxpar", "Othelen", "Padan", "Palid", "Palpur", "Peitar", 
		"Pendus", "Penduhl", "Pildoor", "Puthor", "Phar", "Phalloz", "Qidan", "Quid", "Qupar", "Randar", "Raydan", "Reaper", "Relban", "Riandur", "Rikar", "Rismak", "Riss", "Ritic", 
		"Ryodan", "Rysdan", "Rythen", "Rythorn", "Sabalz", "Sadaron", "Safize", "Samon", "Samot", "Secor", "Sedar", "Senic", "Santhil", "Sermak", "Seryth", "Seth", "Shane", "Shard", 
		"Shardo", "Shillen", "Silco", "Sildo", "Silpal", "Sithik", "Oderman", "Sothale", "Staph", "Suktar", "zuth", "Sutlin", "Syr", "Syth", "Sythril", "Talberon", "Telpur", "Temil", 
		"Tamil", "Tempist", "Teslanar", "Tespan", "Tesio", "Thiltran", "Tholan", "Tibers", "Tibolt", "Thol", "Tildor", "Tilthan", "Tobaz", "Todal", "Tothale", "Touck", "Tok", "Tuscan", 
		"Tusdar", "Tyden", "Uerthe", "Uhmar", "Uhrd", "Updar", "Uther", "Vacon", "Valker", "Valyn", "Vectom", "Veldar", "Velpar", "Valot", "Vildher", "Vigoth", "Vilan", "Vildar", "Vi", 
		"Vinkol", "Virdo", "Voltain", "Wanar", "Wekmar", "Weshin", "Witfar", "Wrath", "Waytel", "Wahmon", "Wider", "Wyeth", "Xandar", "Xavor", "Xenil", "Xelx", "Xithyl", "Yerpal", 
		"Yesirn", "Ylzik", "Zak", "Zek", "Zerin", "Zestor", "Zidar", "Zigmal", "Zilex", "Zilz", "Zio", "Zotar", "Zutar", "Zytan"
	);
	$prefix_male = $wow_male;
	$prefix_female = $wow_female;
	$suffix = $wow_surname;
	$n1m = rand(0, count($prefix_male)-1); //parseInt(Math.random() * $prefix_male.length);
	$n1f = rand(0, count($prefix_female)-1); //parseInt(Math.random() * $prefix_female.length);
	$n2 = rand(0, count($suffix)-1); //parseInt(Math.random() * $suffix.length);
	$n2ekstra = rand(0, count($suffix)-1); //parseInt(Math.random() * $suffix.length);
	$prename_male = ucfirst($prefix_male[$n1m]); //.slice(0, 1).toUpperCase() . $prefix_male[n1m].slice(1);
	$prename_female = ucfirst($prefix_female[$n1f]); //.slice(0, 1).toUpperCase() . $prefix_female[n1f].slice(1);
	$sufname = ucfirst($suffix[$n2]); //.slice(0, 1).toUpperCase() . $suffix[n2].slice(1);
	$extraname = ucfirst($suffix[$n2ekstra]); //.slice(0, 1).toUpperCase() . $suffix[n2ekstra].slice(1);
	$n3 = rand(0,100);
	if($n3 <= 15){
		if(strlen($sufname) < 6){
			$name = $prename_male . strtolower($sufname); //.toLowerCase()
		}else{
			$name = $prename_male . ' ' . $sufname;
		}
	}elseif($n3 > 15 && $n3 <= 25){
		$name = $sufname;
	}elseif($n3 > 25 && $n3 <= 50){
		$name = $prename_male;
	}elseif($n3 > 50 && $n3 <= 70){
		$name = $prename_female;
	}elseif($n3 > 70 && $n3 <= 80){
		$name = $prename_male . strtolower($sufname); //.toLowerCase()
	}elseif($n3 > 80 && $n3 <= 85){
		$name = $prename_female . strtolower($sufname); //.toLowerCase()
	}elseif($n3 > 85 && $n3 <= 92){
		if(strlen($sufname) < 6){
			$name = $prename_female . strtolower($sufname); //.toLowerCase()
		}else{
			$name = $prename_female . ' ' . $sufname;
		}
	}elseif($n3 > 92 && $n3 <= 97){
		if(strlen($prename_male) . strlen($sufname) > 10){
			$name = $prename_male . strtolower($sufname);
		}else{
			$name = $prename_male . ' ' . $extraname . strtolower($sufname);
		}
	}elseif($n3 > 97){
		if(strlen($prename_female) . strlen($sufname) > 10){
			$name = $prename_female . strtolower($sufname);
		}else{
			$name = $prename_female . strtolower($extraname) . ' ' . $sufname;
		}
	}
	return $name;
}

function nickname(){
	$e = array(
		"white", "black", "yellow", "red", "blue", "green", "purple", "orange", "silver", "scarlet", "rainbow", "indigo", "ivory", "navy", "pink", "gold", "golden"
	);
	$t = array(
		"agent", "alpha", "angry", "bad", "bad", "bandit", "barbaric", "bare", "baroness", "beau", "beauty", "beauty", "bitter", "boiling", "brave", "brave", "brutal", 
		"captain", "captain", "captain", "chaos", "chaos", "chicken", "circus", "colonel", "color", "cool", "count", "countess", "crazy", "crisp", "cruel", "crunchy", 
		"cult", "cute", "cute", "cute", "cutie", "dame", "dancing", "dangerous", "desire", "dirty", "disco", "doc", "doc", "doc", "doctor", "doctor", "doctor", "dog", 
		"doggy", "dreaded", "dreaded", "drunken", "drunken", "duck", "duke", "dusty", "eager", "elastic", "endless", "eternal", "fast", "fast", "fatty", "fisty", "flaming", 
		"flower", "flying", "forgotten", "forsaken", "foxy", "freaky", "frozen", "furious", "general", "grim", "grotesque", "gruesome", "gutsy", "hearty", "heavy", "heavy", 
		"helpless", "hidden", "hilarious", "homeless", "honey", "honey", "hot", "hungry", "hungry", "icy", "insane", "intense", "itchy", "kid", "kiddo", "king", "king", 
		"king", "knight", "lady", "lady", "lady", "left-handed", "lefty", "lieutenant", "liquid", "little", "lone", "lone", "lonesome", "loose", "lost", "lucky", "madam", 
		"madam", "madam", "major", "major", "massive", "maxi", "maximum", "meaty", "mellow", "mini", "minimum", "miss", "mister", "mistress", "misty", "modern", "morbid", 
		"moving", "mysterious", "nasty", "needless", "nervous", "old", "pet", "pointless", "prince", "princess", "pure", "queen", "rapid", "rare", "raw", "rebel", "reborn", 
		"richy", "rider", "risky", "rocking", "rocky", "rolling", "rotten", "rough", "running", "runny", "rusty", "rusty", "ruthless", "sad", "screaming", "screamy", "sergeant", 
		"sergent", "serious", "seriously", "sherif", "shining", "silly", "sir", "skilled", "skinny", "sleepy", "sleepy", "slidy", "slimy", "small", "small", "smokey", "solid", 
		"solid", "spacy", "steamy", "stoned", "stony", "stony", "stormy", "stormy", "strange", "stray", "streaming", "strong", "strong", "stupid", "sugar", "sunny", "sunny", 
		"supersonic", "sweet", "sweet", "sweety", "swift", "tainted", "tasty", "thirsty", "tidy", "tiny", "tough", "ugly", "unique", "vicious", "viscountess", "vital", "warm", 
		"wild", "willy", "wooden", "worthy", "young"
	);
	$n = array(
		"alligator", "angel", "antelope", "ape", "armadillo", "baboon", "baby", "baby", "baron", "basilisk", "bat", "bear", "bear", "beaver", "bella", "bird", "birdie", "bison", 
		"boar", "boy", "buffalo", "bull", "bunny", "bunny", "butterfly", "camel", "canary", "cat", "cat", "chameleon", "cheetah", "chick", "child", "chimpanzee", "chinchilla", 
		"chipmunk", "cobra", "cockroach", "colt", "cougar", "cow", "coyote", "crocodile", "crow", "cub", "darling", "deer", "dingo", "doe", "doe", "dog", "dog", "dog", "doggy", 
		"doll", "donkey", "dormouse", "dromedary", "duck", "duckbill", "duckie", "duckling", "dugong", "eagle", "eaglet", "elephant", "elf", "fairy", "farrow", "filly", "finch", 
		"fish", "flapper", "flipper", "foal", "fox", "fox", "frog", "froglet", "gazelle", "giraffe", "girl", "gnu", "goat", "gorilla", "grizzly", "guinea", "hamster", "hare", 
		"hatchling", "hawk", "hippopotamus", "hog", "honey", "honey", "horse", "hyena", "ibis", "impala", "infant", "iris", "jackal", "jaguar", "joey", "kangaroo", "kid", "kid", 
		"kiddie", "king", "kit", "kitten", "kitten", "koala", "lama", "lamb", "lamb", "larva", "lemur", "leopard", "lion", "lion", "lizard", "llama", "lovebird", "lynx", "man", 
		"mandrill", "mare", "mink", "mole", "monkey", "moose", "moose", "moose", "mouse", "mouse", "mule", "musk-ox", "mustang", "nymph", "ocelot", "opossum", "orangutan", "otter", 
		"ox", "panda", "panther", "panther", "parakeet", "parrot", "pet", "pig", "pig", "piglet", "pink", "pinkie", "polar-bear", "pony", "prince", "puglet", "puma", "pup", "puppy", 
		"puppy", "python", "queen", "rabbit", "rabbit", "raccoon", "rat", "rat", "reindeer", "reptile", "rhino", "salamander", "seal", "serpent", "sheep", "skunk", "snake", "snake", 
		"sparrow", "spider", "spider", "springbok", "squirrel", "stallion", "sugar", "swallow", "swan", "sweety", "tapir", "tiger", "tiger", "toad", "toddler", "tumbler", "turtle", 
		"viper", "walrus", "waterbuck", "weasel", "whale", "whelp", "wildcat", "wolf", "wolverine", "wombat", "woodchuck", "wriggler", "yak", "zebra"
	);
	$r = array(
		"airmen", "beast", "believer", "bullet", "swush", "cadet", "dancer", "demon", "detective", "devil", "dolly", "dummy", "empire", "fever", "fiend", "fisherman", "freak", 
		"gangster", "gazette", "genius", "gladiator", "goldfish", "goldbeast", "gravy", "hammer", "harmony", "invader", "jockey", "judge", "juggler", "king", "lady", "lord", 
		"mutant", "phantom", "pilot", "pioneer", "pirate", "prisoner", "professor", "prophet", "ranger", "rebel", "romeo", "saint", "shadow", "sinner", "student", "titan", 
		"trooper", "stud", "trustee", "villain", "volunteer", "warrior", "yodelers", "baroness", "beam", "breeze", "burst", "crystal", "emerald", "galaxy", "hammer", "hook", 
		"hurricane", "iron", "knife", "laser", "moon", "moron", "rayz", "sapphire", "scissor", "space", "star", "steel", "storm", "sun"
	);
	$i = array_merge($e,$t); //e.concat($t);
	$s = array_merge($n,$r); //$n.concat(r);
	$o = rand(0,count($i)-1); //parseInt(Math.random() * i.length);
	$u = rand(0,count($i)-1); //parseInt(Math.random() * i.length);
	if($u == $o){
		$u = $o + 1;
	}
	$a = rand(0,count($s)-1); //parseInt(Math.random() * s.length);
	$f = ucfirst($i[$o]); //i[o].slice(0, 1).toUpperCase() + i[o].slice(1);
	$l = ucfirst($i[$u]); //i[u].slice(0, 1).toUpperCase() + i[u].slice(1);
	$c = ucfirst($s[$a]); //s[a].slice(0, 1).toUpperCase() . s[a].slice(1);
	$h = rand(0,100);
	if($h <= 30){
		$name = $f . " " . $c;
	}elseif($h > 30 && $h <= 40){
		$name = $l . " " . $c;
	}elseif($h > 40 && $h <= 65){
		$name = $c . " " . $l;
	}elseif($h > 65 && $h <= 84){
		$name = $l . " " . $f . " " . $c;
	}elseif($h > 84 && $h <= 88){
		$name = $c . $c;
	}elseif($h > 88 && $h <= 90){
		$name = "Los " . $c;
	}elseif($h > 90 && $h <= 92){
		$name = "Der " . $c;
	}elseif($h > 92 && $h <= 94){
		$name = "El " . $c;
	}else{
		$name = "The " . $c;
	}
	return $name;
}

function vampirename(){
	$a = array("white", "black", "red", "bloody", "purple", "silver", "scarlet", "lightning");
	$b = array("Count", "Lord", "Prince", "Darklord", "Count");
	$c = array("Lady", "Lady", "Comtesse", "Princess");
	$d = array(
		"abardon", "acaman", "achard", "ackmard", "actar", "adel", "agnar", "agon", "albrecht", "alexander", "anfarc", "armand", "arturo", "azazel", "badek", 
		"bein", "bolrock", "boris", "bryce", "cecil", "christian", "cyril", "cyrus", "cytor", "daburn", "dactor", "damien", "damion", "dante", "darg", "darmor", 
		"darpick", "dask", "deathmar", "derik", "dimitri", "dismer", "doctor", "dokohan", "doktor", "doran", "dorian", "dorn", "drakon", "drit", "driz", "eldar", 
		"etar", "gabriel", "gothar", "gothor", "greste", "grim", "hades", "ignacio", "isaak", "israfel", "ivan", "izic", "janik", "julius", "kafar", "kaldar", "kildaz", 
		"kildiz", "ladok", "lector", "ledal", "loban", "ludok", "magnus", "marius", "marquis", "mezo", "mizar", "nathaniel", "nicholas", "nicolos", "nilex", "nizel", 
		"padaz", "palid", "percy", "piotr", "rafael", "raphael", "rizar", "salomon", "secor", "senic", "sergei", "silpaz", "slavik", "slovak", "tibolt", "tobaz", "tocaz", 
		"tristan", "veldar", "vigoth", "viktor", "viktor", "vilder", "vlad", "vladi", "vladimir", "vladimir", "vlador", "xavier", "zak", "zander", "zane", "zek", "zerin", 
		"zestor", "zidar", "zigmal", "zilex", "zilz", "zio", "zotar", "zutar", "zytan"
	);
	$e = array(
		"ada", "adorra", "akia", "ana", "asada", "briana", "frederika", "frida", "idona", "illia", "irene", "irina", "irona", "kassina", "lana", "lelana", "leluna", "luna", 
		"lynessa", "narisa", "olivia", "onathe", "rhina", "senira", "sennetta", "sidara", "sidathe", "sina", "teressa", "teressa", "thea", "useli", "uza", "zama", "zana", 
		"zara", "zecele", "zenobe", "zestia", "zilka", "zoca", "zouca", "zynoa", "adamantha", "alexandra", "angelica", "angelina", "ariel", "asta", "audrey", "aurora", 
		"beatrice", "bella", "belladonna", "celine", "charlotte", "comtessa", "comtessa", "cybelia", "cynthia", "dementia", "desdemona", "desiree", "edith", "elenor", "faith", 
		"gabriella", "hecate", "heidi", "ilse", "irana", "iris", "jezebel", "kalia", "katja", "kristin", "lana", "lilith", "lillith", "luna", "lydia", "magdelena", "marcia", 
		"marishka", "medea", "melancholia", "mirabella", "misha", "morganna", "natasha", "nina", "ophelia", "pedita", "regna", "rosalie", "ruby", "sabrina", "sonia", "theodora", 
		"titania", "ursula", "vanessa", "veronika", "zena"
	);
	$f = array(
		"adams", "alberict", "allegheri", "armand", "asiman", "azariel", "blackraven", "blackstroker", "bloodrayne", "coldbane", "crawhawk", "cryptmaw", "darkblade", "darkblood", 
		"darkland", "darkmoon", "darkton", "darkwood", "de la ezma", "deadwood", "dementoio", "drakul", "dreadweep", "dybbuk", "eising", "eldritch", "ethelred", "fargloom", 
		"fogripper", "gallowsraven", "ghostfire", "ghoulblade", "griefstrike", "grimdark", "grimmel", "grimrage", "grimryder", "harker", "hawkings", "iceshiard", "icing", "incubus", 
		"kensington", "ladislav", "le mort", "lestat", "macbath", "mistfang", "moonbeam", "morbid", "mordant", "morganthe", "morrisey", "necrophilip", "nephilim", "obayifo", "orlock", 
		"rakshasas", "ravenblack", "ravencrypt", "ravengric", "ravengrim", "ravenlock", "ravenryder", "reaper", "requiem", "sangre", "shelley", "slavanovitz", "slavik", "snakecrypt", 
		"spidergrim", "stoker", "tapetes", "thanatos", "vampir", "van cruel", "van dirge", "van locker", "vanislav", "vhampir", "visigoth", "vladimir", "von dracul", "von drakon", 
		"von elfstein", "von richter", "von slavik", "von wolf", "winters", "wormwood"
	);
	$g = array(
		"arsenic", "barbarian", "barbaric", "bitter", "blood", "bloody", "brutal", "crypt", "dangeir", "deathstrike", "demon", "despair", "devil", "devil", "devilish", "devine", 
		"drak", "dreaded", "eastern", "endless", "eternal", "evil", "forsaken", "frozen", "furious", "garlic", "gruesome", "hunger", "insane", "moonlight", "morbid", "nastly", 
		"nocturne", "pale", "raven", "raving", "reborn", "redtooth", "ruthless", "schleepi", "schneeky", "screaming", "silver", "striki", "vicious", "vinther"
	);
	$h = array_merge($g,$a); //g.concat(a);
	$i = $d;
	$j = $e;
	$k = $f;
	$l = rand(0,count($i)-1); //parseInt(Math.random() * i.length);
	$m = rand(0,count($j)-1); //parseInt(Math.random() * j.length);
	$n = rand(0,count($h)-1); //parseInt(Math.random() * h.length);
	$o = rand(0,count($k)-1); //parseInt(Math.random() * k.length);
	$p = ucfirst($i[$l]); //i[l].slice(0, 1).toUpperCase() + i[l].slice(1);
	$q = ucfirst($j[$m]); //j[m].slice(0, 1).toUpperCase() + j[m].slice(1);
	$r = ucfirst($h[$n]); //h[n].slice(0, 1).toUpperCase() + h[n].slice(1);
	$s = ucfirst($k[$o]); //k[o].slice(0, 1).toUpperCase() + k[o].slice(1);
	$t = rand(0,100);
	if($t <= 30){
		$name = $p . " " . $s;
	}elseif($t > 30 && $t <= 50){
		$name = $q . " " . $s;
	}elseif($t > 50 && $t <= 65){
		$name = $p . " " . $r;
	}elseif($t > 65 && $t <= 80){
		$name = $q . " " . $r;
	}elseif($t > 80 && $t <= 90){
		$name = $r . " " . $p;
	}elseif($t > 90){
		$name = $r . " " . $q;
	}
	if($t <= 10){
		$name = $b[$t % 3] . " " . $name;
	}
	if($t > 50 && $t < 57){
		$name = $b[$t % 3] . " " . $name;
	}
	if($t > 80 && $t < 83){
		$name = $b[$t % 3] . " " . $name;
	}
	if($t > 65 && $t < 70){
		$name = $c[$t % 4] . " " . $name;
	}
	if($t > 30 && $t < 35){
		$name = $c[$t % 4] . " " . $name;
	}
	if($t > 93){
		$name = $c[$t % 4] . " " . $name;
	}
	return $name;
}

function pirate_name(){
	$color = array("black", "bloody", "crimson", "gold", "gray", "purple", "red", "scarlet", "silver", "white");
	$pirate_title = array("Captain", "Salty", "Bloody", "Captain", "Capt'n");
	$pirate_male = array(
		"bill", "billy", "brutus", "edward", "francois", "george", "henri", "jack", "jack", "jack", "jacob", "james", "joe", "john", "john", "johnny", 
		"nathan", "peter", "roger", "simon", "steve", "thomas", "walker", "william"
	);
	$pirate_female = array(
		"angel", "anne", "bella", "carolina", "charlotte", "dana", "donna", "grace", "jane", "kala", "katja", "lana", "lena", "luna", "marcia", "margaret", 
		"maria", "mary", "misha", "nana", "rachel", "rosa", "ruby", "sally", "scarlet", "sofia", "tessa", "vanessa"
	);
	$pirate_surname = array(
		"black", "blackbeard", "blackman", "blacksmith", "blackstroker", "bloodrayne", "coldbane", "crawhawk", "darkblade", "darkblood", "darkmoon", "davis", 
		"de Belleville", "deadwood", "digger", "dreadbeard", "fargloom", "grimbeard", "gull", "gully", "harker", "hawkins", "klek", "moonship", "ravenbeard", 
		"ravenblack", "redbeard", "redblade", "sangre", "scarlet", "scully", "seagull", "shelley", "silverbeard", "silverblade", "silvergrim", "stoker", "white", "wormwood"
	);
	$matter = array(
		"avast", "barbarian", "barbaric", "belay", "blimey", "blood", "bloody", "brutal", "bucket", "canon", "chest", "corsair", "dangerous", "dark", "deadlights", "demon", 
		"devil", "dirty", "dog", "doubloon", "evil", "gross", "grub", "gruesome", "hungry", "insane", "keelhaul", "keg", "killer", "moony", "morbid", "nasty", "one Legged", 
		"pale", "raving", "reborn", "redtooth", "rum", "ruthless", "salty", "screaming", "scurvy", "sea dog", "shanty", "silver", "sneaky", "stormy", "swag", "toothless", "vicious", "windy"
	);
	$extra = array_merge($matter, $color); //matter.concat(color);
	$prefix_male = $pirate_male;
	$prefix_female = $pirate_female;
	$suffix = $pirate_surname;
	$n1m = rand(0, count($prefix_male)-1); //parseInt(Math.random() * $prefix_male.length);
	$n1f = rand(0, count($prefix_female)-1); //parseInt(Math.random() * $prefix_female.length);
	$n2 = rand(0, count($suffix)-1); //parseInt(Math.random() * $suffix.length);
	$n2ekstra = rand(0, count($suffix)-1); //parseInt(Math.random() * $suffix.length);
	$prename_male = ucfirst($prefix_male[$n1m]); //.slice(0, 1).toUpperCase() . $prefix_male[n1m].slice(1);
	$prename_female = ucfirst($prefix_female[$n1f]); //.slice(0, 1).toUpperCase() . $prefix_female[n1f].slice(1);
	$sufname = ucfirst($suffix[$n2]); //.slice(0, 1).toUpperCase() . $suffix[n2].slice(1);
	$extraname = ucfirst($suffix[$n2ekstra]); //.slice(0, 1).toUpperCase() . $suffix[n2ekstra].slice(1);
	$n3 = rand(0,100);
	if($n3 <= 25){
		$name = $prename_male . " " . $sufname;
	}elseif($n3 > 25 && $n3 <= 35){
		$name = $prename_female . " " . $sufname;
	}elseif($n3 > 35 && $n3 <= 55){
		$name = $prename_male . " " . $extraname . " " . $sufname;
	}elseif($n3 > 55 && $n3 <= 90){
		$name = $extraname . " " . $prename_male . " " . $sufname;
	}elseif($n3 > 90 && $n3 <= 96){
		$name = $extraname . " " . $prename_female . " " . $sufname;
	}elseif($n3 > 96){
		$name = $prename_female . " " . $extraname . " " . $sufname;
	}
	if($n3 <= 15 || ($n3 > 25 && $n3 <= 30)){
		$name = $pirate_title[$n3 % 5] . " " . $name;
	}
	return $name;
}

function projectname(){
	$color = array(
		"white", "black", "yellow", "red", "blue", "brown", "green", "purple", "orange", "silver", "scarlet", "rainbow", "indigo", "ivory", "navy", "olive", "teal", 
		"pink", "magenta", "maroon", "sienna", "gold", "golden"
	);
	$adjective = array(
		"abandoned", "aberrant", "accidentally", "aggressive", "aimless", "alien", "angry", "appropriate", "barbaric", "beacon", "big", "bitter", "bleeding", "brave", 
		"brutal", "cheerful", "dancing", "dangerous", "dead", "deserted", "digital", "dirty", "disappointed", "discarded", "dreaded", "eastern", "eastern", "elastic", 
		"empty", "endless", "essential", "eternal", "everyday", "fierce", "flaming", "flying", "forgotten", "forsaken", "freaky", "frozen", "full", "furious", "ghastly", 
		"global", "gloomy", "grim", "gruesome", "gutsy", "helpless", "hidden", "hideous", "homeless", "hungry", "insane", "intense", "intensive", "itchy", "liquid", "lone", 
		"lost", "meaningful", "modern", "monday's", "morbid", "moving", "needless", "nervous", "new", "next", "ninth", "nocturnal", "northernmost", "official", "old", 
		"permanent", "persistent", "pointless", "pure", "quality", "random", "rare", "raw", "reborn", "remote", "restless", "rich", "risky", "rocky", "rough", "running", 
		"rusty", "sad", "saturday's", "screaming", "serious", "severe", "silly", "skilled", "sleepy", "sliding", "small", "solid", "steamy", "stony", "stormy", "straw", 
		"strawberry", "streaming", "strong", "subtle", "supersonic", "surreal", "tainted", "temporary", "third", "tidy", "timely", "unique", "vital", "western", "wild", 
		"wooden", "worthy", "bitter", "boiling", "brave", "cloudy", "cold", "confidential", "dreadful", "dusty", "eager", "early", "grotesque ", "harsh", "heavy", "hollow", 
		"hot", "husky", "icy", "late", "lonesome", "long", "lucky", "massive", "maximum", "minimum", "mysterious", "outstanding", "rapid", "rebel", "scattered", "shiny", 
		"solid", "square", "steady", "steep", "sticky", "stormy", "strong", "sunday's", "swift", "tasty"
	);
	$science = array(
		"alarm", "albatross", "anaconda", "antique", "artificial", "autopsy", "autumn", "avenue", "backpack", "balcony", "barbershop", "boomerang", "bulldozer", "butter", 
		"canal", "cloud", "clown", "coffin", "comic", "compass", "cosmic", "crayon", "creek", "crossbow", "dagger", "dinosaur", "dog", "donut", "door", "doorstop", "electrical", 
		"electron", "eyelid", "firecracker", "fish", "flag", "flannel", "flea", "frostbite", "gravel", "haystack", "helium", "kangaroo", "lantern", "leather", "limousine", 
		"lobster", "locomotive", "logbook", "longitude", "metaphor", "microphone", "monkey", "moose", "morning", "mountain", "mustard", "neutron", "nitrogen", "notorious", 
		"obscure", "ostrich", "oyster", "parachute", "peasant", "pineapple", "plastic", "postal", "pottery", "proton", "puppet", "railroad", "rhinestone", "roadrunner", "rubber", 
		"scarecrow", "scoreboard", "scorpion", "shower", "skunk", "sound", "street", "subdivision", "summer", "sunshine", "tea", "temple", "test", "tire", "tombstone", "toothbrush", 
		"torpedo", "toupee", "trendy", "trombone", "tuba", "tuna", "tungsten", "vegetable", "venom", "vulture", "waffle", "warehouse", "waterbird", "weather", "weeknight", 
		"windshield", "winter", "wrench", "xylophone", "alpha", "arm", "beam", "beta", "bird", "breeze", "burst", "cat", "cobra", "crystal", "drill", "eagle", "emerald", 
		"epsilon", "finger", "fist", "foot", "fox", "galaxy", "gamma", "hammer", "heart", "hook", "hurricane", "iron", "jazz", "jupiter", "knife", "lama", "laser", "lion", 
		"mars", "mercury", "moon", "moose", "neptune", "omega", "panther", "planet", "pluto", "plutonium", "poseidon", "python", "ray", "sapphire", "scissors", "screwdriver", 
		"serpent", "sledgehammer", "smoke", "snake", "space", "spider", "star", "steel", "storm", "sun", "swallow", "tiger", "uranium", "venus", "viper", "wrench", "yard", "zeus"
	);
	$prefix = array_merge($color,$adjective);//color.concat(adjective);
	$suffix = $science;
	$n1 = rand(0,count($prefix)-1); //parseInt(Math.random() * $prefix.length);
	$n1ex = rand(0,count($prefix)-1); //parseInt(Math.random() * $prefix.length);
	if($n1ex == $n1){
		$n1ex = $n1 + 1;
	}
	$n2 = rand(0,count($suffix)-1); //parseInt(Math.random() * $suffix.length);
	$prename = ucfirst($prefix[$n1]); //$prefix[n1].slice(0, 1).toUpperCase() . $prefix[n1].slice(1);
	$prenameex = ucfirst($prefix[$n1ex]); //$prefix[n1ex].slice(0, 1).toUpperCase() . $prefix[n1ex].slice(1);
	$sufname = ucfirst($suffix[$n2]); //$suffix[n2].slice(0, 1).toUpperCase() . $suffix[n2].slice(1);
	$n3 = rand(0,100);
	if($n3 <= 15){
		$name = $prenameex . " " . $prename . " " . $sufname;
	}elseif($n3 > 15 && $n3 <= 30){
		$name = $sufname . " " . $prename;
	}else{
		$name = $prename . " " . $sufname;
	}
	return $name;
}
