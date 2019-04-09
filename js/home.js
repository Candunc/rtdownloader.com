var channels = ["rooster-teeth", "achievement-hunter", "funhaus", "inside-gaming", "screwattack", "sugar-pine-7", "cow-chop", "game-attack", "jt-music", "kinda-funny"];
var tile = document.getElementById("tile-area");

function buildTiles(data, status) {
	var videos = data;
	var inner = "";

	for (var i = 0; i < videos.length; i++) {
		inner += '<div class="col-md-4">' +
			'<div class="card mb-4 box-shadow">' +
			'<img class="card-img-top" src="' + videos[i]["image"] + '">' +
			'<div class="card-body"><p class="card-text">' + videos[i]["title"] + '</p>' +
			'<p class="title-text"><a href="https://roosterteeth.com/series/' + videos[i]["show_slug"] + '">' + videos[i]["show_title"] + '</a></p>' +
			'<div class="d-flex justify-content-between align-items-center">' +
			'<div class="btn-group">' +
			'<a class="btn btn-sm btn-outline-secondary" href="https://roosterteeth.com' + videos[i]["canonical_link"] + '">More Info</a>' +
			'<a class="btn btn-sm btn-outline-secondary" href="api.php?action=m3u8&slug=' + videos[i]["slug"] + '">Download</a></div>' +
			'<img class="card-icon hide-img" src="img/' + videos[i]["channel"] + '.png">' +
			'</div></div></div></div>\n';
	}

	document.getElementById("tile-area").innerHTML = inner;

	return inner;
}

function updateTiles(channel) {
	if (channel === "all") {
		$.get("api.php?action=getEpisodes", buildTiles);
	} else {
		$.get("api.php?action=getEpisodes&channel=" + channel, buildTiles);
	}

	document.getElementById("menu-" + previous).classList.remove("active");
	document.getElementById("menu-" + channel).classList.add("active");

	fsWrite("last_channel", channel);
	previous = channel;
}


// https://stackoverflow.com/a/11986895/1687505
function loopBullshit(evt) {
	updateTiles(evt.target.channel);
}

var previous = fsRead("last_channel");
if (previous === "") {
	previous = "all";
}

updateTiles(previous);

var item;
document.getElementById("menu-all").addEventListener("click",function(){updateTiles("all")});
for (var i = 0; i < channels.length; i++) {
	item = document.getElementById("menu-" + channels[i]);
	item.addEventListener("click", loopBullshit);
	item.channel = channels[i];
}
