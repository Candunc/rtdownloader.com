function fsRead(key) {
	if (typeof localStorage !== 'undefined') {
		var data = localStorage.getItem(key);
		if (data === null) {
			return "";
		} else {
			return data;
		}
	} else {
		console.log("localStorage is unavailable. This website will not work as intended.")
	}
}

function fsWrite(key,value) {
	if (typeof localStorage !== 'undefined') {
		localStorage.setItem(key, value);
	} else {
		console.log("localStorage is unavailable. This website will not work as intended.")
	}
}
