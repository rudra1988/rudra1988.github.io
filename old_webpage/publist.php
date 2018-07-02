<?

//
// PubList v1.0, a little script for automating the generation
// of a list of scientific publications.
// Copyright (C) 2006 - Alejandro G. Stankevicius.
//
// This script is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or any later version.
//
// This script is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
// without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
// See the GNU General Public License for more details.
//

class Publication { // Publications are just regular files

	var $name;
	var $path;
	var $info;

	function Build($path, $name) {
		$this->name = $name;
		$this->path = $path;
 		$this->info = pathinfo($name);
	}	

	function getPath () {
		return $this->path;
	}

	function getName () {
		return $this->name;
	}

	function getExtension() {
		return $this->info['extension'];
	}

	function getBasename () { // Note that by "basename" we mean the file name without its extension
		return substr($this->info['basename'], 0, strpos($this->info['basename'], "."));
	}

	function getFullname () {
		return $this->path . $this->name;
	}
}

class Citation { // Citations contain all the information we need to extract from .bib files

	// These are all the relevant bibtex fields we need (for now)
	var $year;
	var $title;
	var $author;
	var $booktitle;
	var $journal;
	var $volume;
	var $number;

	// Citations also keep a link to the original .bib file
	var $publication;

	function getPublication() {
		return $this->publication;
	}

	function getYear() {
		return $this->year;
	}

	function getAuthor() {
		return $this->author;
	}

	function getTitle() {
		return $this->title;
	}

	function getBooktitle() {
		return $this->booktitle;
	}

	function getJournal() {
		return $this->journal;
	}

	function getVolume() {
		return $this->volume;
	}

	function getNumber() {
		return $this->number;
	}

	function Parse($citation){ // Apply some regex magic to the .bib file content

		// Get rid of the bibitem type...
		$citation=ereg_replace("@[^,]*,", '', $citation);

		// ...also of those annoying LFs and CRs...
		$citation=str_replace("\n", ' ', $citation);
		$citation=str_replace("\r", '', $citation);

		// ...drop the capitalization aids...
		$citation=str_replace('{', '', $citation);
		$citation=str_replace('}', '', $citation);

		// ...also drop tex & latex commands for high ascii chars...
		$citation = strtr($citation, array("\'a" => 'á', "\'e" => 'é', "\'\i" => 'í', "\'o" => 'ó', "\'u" => 'ú', "\~n" => 'ñ'));

		// ...and then mark the end of every keyword-value pair
	        $citation=ereg_replace("[[:blank:]]*=[[:blank:]]*", '=', $citation);
        	$citation=ereg_replace("([[:upper:]]+=\"[^\"]*\")[,)]", '\\1::', $citation);
	        $citation=ereg_replace("([[:upper:]]+=[^,^\"^ ]*)[[[:blank:]]*[,)]", '\\1::', $citation);

		// Finally, lets decode the citation content
		$fields=explode('::', $citation);

		// Once decoded, set the local variables accordingly
		for($index=0; $index<count($fields); $index++) {
			list($keyword, $value) = explode('=', $fields[$index]);
			$keyword = strtoupper(ereg_replace("[[:blank:]]", '', $keyword));
			$value = str_replace('"', '', $value);
			switch ($keyword) {

				case 'AUTHOR';
					$authors = explode(' and ', $value);

					switch (count($authors)) { // Poor man's version of bibtex author handling ;)

						case 1;
							$this->author = $value;
							break;

						case 2;
							$this->author = $authors[0] . ' and ' . $authors[1];
							break;

						case 3;
							$this->author = $authors[0] . ', ' . $authors[1] . ' and ' . $authors[2];
							break;

						default:
							$this->author = $authors[0] . ', ' . $authors[1] . ', et al';
					}

					break;

				case 'TITLE';
					$this->title = $value;
					break;

				case 'YEAR';
					$this->year = $value;
					break;

				case 'BOOKTITLE';
					$this->booktitle = $value;
					break;
	
				case 'JOURNAL';
					$this->journal = $value;
					break;

				case 'VOLUME';
					$this->volume = $value;
					break;

				case 'NUMBER';
					$this->number = $value;
					break;
			}
		}
	}

	function Build($publication) { // Takes the content of a bibtex file and decodes it

		$this->publication=$publication;

		// $this->Parse(file_get_contents($this->publication->getFullname())); // This requires >4.3.0
		
		$bibtex = file($this->publication->getFullname());
		$citation = '';
		for ($index=0; $index<count($bibtex); $index++) // Less elegant, but backward compatible
			$citation = $citation . $bibtex[$index];
		$this->Parse($citation);
	}
}

function recent_first($first, $second) { // An auxiliary criterion for ordering the publications
	if ($first[0] == $second[0])
		return 0;
	else
    		return ($first[0] < $second[0]) ? 1 : -1;
}

function recent_last($first, $second) { // Another auxiliary criterion for ordering the publications
	if ($first[0] == $second[0])
		return 0;
	else
    		return ($first[0] < $second[0]) ? -1 : 1;
}

function PubList($folder, $criterion) { // Main fuction, which from a folder and an ordering criterion creates the list of publications 

	// Check whether we have the required trailing slash
	if (substr($folder, -1) != '/') {
		$folder = $folder . '/';
	}

	$lsla = opendir($folder);

	// Load all the folder content into our local arrays
	while($file = readdir($lsla)) {

		$publication = new Publication();
		$publication->Build($folder, $file);

		switch (strtoupper($publication->getExtension())) { // Sort the different files found according to their extension

			case 'BIB';
				$citation = New Citation();
				$citation->Build($publication);
				$BIBs[$publication->getBasename()] = $citation;
				break;

			case 'PDF';
				$PDFs[$publication->getBasename()] = $publication;
				break;

			case 'DVI';
				$DVIs[$publication->getBasename()] = $publication;
				break;

			case 'PS';
				$PSs[$publication->getBasename()] = $publication;
				break;

			case 'GZ';
				$PSZs[$publication->getBasename()] = $publication;
				break;
		}
	}

	foreach ($BIBs as $paper => $citation) { // We are using the name without its extension as the index of these arrays

		$item = '<b>' . $citation->getTitle() . '.</b> ' . $citation->getAuthor();
        if ( $citation->getJournal() ) {
			$item = $item . '. <i>' . $citation->getJournal() . '</i>, ' . $citation->getVolume() . '(' . $citation->getNumber() . ')' . ', ';
		} else {
			$item = $item . '. In <i>' . $citation->getBooktitle() . '</i>, ';
		}
		$item = $item .  $citation->getYear() . '. Available as:';

		$links='';
		if (!empty($PDFs) && isset($PDFs[$paper])) {
			$links=$links . ' <A HREF="' . $PDFs[$paper]->getFullname() . '">[PDF]</A>';
		}
		if (!empty($PSs) && isset($PSs[$paper])) {
			$links=$links . ' <A HREF="' . $PSs[$paper]->getFullname() . '">[PS]</A>';
		}
		if (!empty($PSZs) && isset($PSZs[$paper])) {
			$links=$links . ' <A HREF="' . $PSZs[$paper]->getFullname() . '">[PSZ]</A>';
		}
		if (!empty($DVIs) && isset($DVIs[$paper])) {
			$links=$links . ' <A HREF="' . $DVIs[$paper]->getFullname() . '">[DVI]</A>';
		}

                $bibfile = $citation->getPublication();
		$links = $links . ' <A HREF="' . $bibfile->getFullname() . '">[BiBTeX]</A>';
		
		$items[] = array($citation->getYear(), $item . $links); // Keep the HTML code in an array for easy reordering
	}

	usort($items, $criterion); // Apply the supplied criterion

	// Here we can output the content of the publication list as we see fit... happy hacking!
	echo "<UL>\n";
	foreach ($items as $index => $entry)
		echo "<li>$entry[1]</li>\n";
	echo "</UL>\n";
}

?>
