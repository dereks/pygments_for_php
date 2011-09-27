<?php

function pygmentize( $code, $language, $style="default", $tabwidth=4, $extra_opts="" ) {

	// Create a temporary file.  This is easier than dealing with PHP's cranky STDOUT handling:
	$temp_name = tempnam( "/tmp", "pygmentize_" );
	
	// We give each block a unique CSS class, so that different chunks
	// on the page can have different styles.  Note that "table" is
	// appended by pygmentize if using a line numbers table.  If we 
	// didn't do this, all CSS definitions would use the default value
	// "highlight", making it impossible to have diffn't styles on the same page.
	$highlight_class = basename( $temp_name );
	
	// Workaround bugfixes for WordPress when using dark-background styles (monokai).
	
	// We need this because the default WP style has a global <pre> font color that is almost
	// invisible against a dark background.
	if ( $style == "monokai" or $style == "fruity" or $style == "vim" ) {
		$linenos_extra_css = "color: #F8F8F2;";
	}
	
	$file_handle = fopen( $temp_name, "w" );
	fwrite( $file_handle, $code );
	fclose( $file_handle );
	
	// Add the "full" and style options:
	if ( $extra_opts == "" ) {
		$extra_opts = "-O full,style=".$style.",cssclass=".$highlight_class;
	} else {
		// Just append these to the passed-in args:
		$extra_opts .= ",full,style=".$style.",cssclass=".$highlight_class;
	}
	
	// Color it.  We depend on pygmentize being in the PATH (prolly in /usr/bin/):
	$command = "pygmentize -f html $extra_opts -l $language $temp_name";
	$output = array();
	$retval = -1;
	
	exec( $command, $output, $retval );
	unlink( $temp_name );
	
	$output_string = join( "\n", $output );

	$original_output_string = join( "\n", $output );
	// Manually wrap tabs in a "tabspan" class, so the tab width can be set to 4
	$output_string = str_replace( "\t", "<span class='tabspan'>\t</span>", $output_string );
	
	// We use the Pygments "full" option, so that we don't need to manage separate 
	// CSS files (and links) for  every possible value of "style".
	// However, "full" exports a full HTML doctype, <title>, <body>, etc. which we don't
	// want when embedding into another PHP document.  So we manually remove that stuff here.
	
	// Replace everything up to (and including) the first <style> tag:
	if ( strpos( $output_string, '<style type="text/css">' ) != FALSE ) {
		$header_ending_position = strpos( $output_string, '<style type="text/css">' ) + strlen( '<style type="text/css">' );
		$output_string = substr( $output_string, $header_ending_position );
	}
	// We also prepend extra CSS info for the tab width and line numbering here.
	$output_string = <<<EOD
<style type="text/css">


/* *************************************************** */
/* WordPress default theme "twenty-ten" compatibility: */
/* FIXME: Is there a way to limit these changes to {$highlight_class}  */
/*        without editing the WordPress theme directly? */
/* *************************************************** */

#content pre {
  /* The background: transparent is needed to work with WordPress:*/
  background: transparent;
  /* Default WordPress style has a big fat <pre> margin-bottom: */
  margin-bottom: 0px;

  /*color: #333333;*/
  font-size: .9em; 
  line-height: 1.25em;
}

#content table {
}

#content table {
	border: 1px solid #e7e7e7;
    margin: 0 0 0 0;
	text-align: left;
	width: 100%;
}
#content tr th,
#content thead th {
	color: #888;
	font-size: 12px;
	font-weight: bold;
	line-height: 18px;
	padding: 0px;
}
#content tr td {
	border: 0px;
	padding: 0px;
	margin: 0px;
    margin-bottom: 0px;
    padding-bottom: 0px;

}

/* *************************************************** */
/* *************************************************** */

/* Standard fixes to the default output: */

/* Set the tab width in "ch" character units: */
.$highlight_class .tabspan {
  display: inline-block;
  width: {$tabwidth}ch;
}

/* When using line numbers, use 100% table width and no cellpadding: */
.{$highlight_class}table {
  width: 100%;
  border-spacing: 0px;
  border-collapse: collapse;
}


/*
#content table {
    border: 1px solid #E7E7E7;
    margin: 0 -1px 24px 0;
    text-align: left;
    width: 100%;
}
*/

.{$highlight_class}table td, .{$highlight_class}table th {
  padding: 0px;
  margin: 0px;
}

/* Add a little buffer so the monotype font doesn't bump directly against the edge: */
.{$highlight_class} pre {
  padding: .6ch;
  $linenos_extra_css
}

/* This is more consistent with <p> tags... I didn't like it for my use: */
/*
div .{$highlight_class} {
    margin-bottom: 24px; 
}
*/

td.linenos.{$highlight_class} {
  width: 1ch;
  padding: .6ch;
  line-height: 1.25em;
}

.{$highlight_class} td {
  padding-right: 1px;
}

$output_string
EOD;
	
	// Remove these other unneeded tags.  (The <h2></h2> is empty because we didn't supply a "title".)
	// Note that other tags (like <html> and <head> were removed above, with the first <style> tag.
	$html_tags_to_remove = array ( "</head>", "<body>", "<h2></h2>", "</body>", "</html>" );
	foreach( $html_tags_to_remove as $tag ) {
		$output_string = str_replace( $tag, "", $output_string );		
	}
	
	// A quirk of pygmentize is that, if you use the "full" option to get CSS and HTML at once,
	// it does not honor the -a option to set the style class.  Instead, it applies the CSS to the 
	// "body" elements.  So here we manually replace those "body" elements with the name of the wrapper class.
	// We only replace "body" up to </style>, so as not to affect the highlighted code.
	// We also make the hardcoded class "linenodiv" match the code style.
	if ( strpos( $output_string, '</style>' ) != FALSE ) {
		$css_ending_position = strpos( $output_string, '</style>' ) + strlen( '</style>' );
		$css_header_part = substr( $output_string, 0, $css_ending_position );
		$html_tail_part = substr( $output_string, $css_ending_position );
		
		// Note, unlike "body", CSS class names have a prepended period.  
		$css_header_part = str_replace( "body", '.'.$highlight_class, $css_header_part );
		
		// I prefer a narrow gap in the line numbers.  I replace the 10 with 1:
		$css_header_part = str_replace( "padding-right: 10px;", 'padding-right: 1px;', $css_header_part );
		
		// Make the linenodiv match the style:
		$html_tail_part = str_replace( '<div class="linenodiv">', '<div class="linenodiv '.$highlight_class.'">', $html_tail_part );
		// Make the linenodiv match the style:
		$html_tail_part = str_replace( '<td class="linenos">', '<td class="linenos '.$highlight_class.'">', $html_tail_part );
		
		$output_string = $css_header_part . $html_tail_part;
	}
	
	
	return $output_string;	
}


