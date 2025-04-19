<?php
$messages_buffer_file = "messages1.json";
$messages_buffer_size = 50;
$enable_chatlog = false;

if ( isset($_POST["content"]) and isset($_POST["name"]) ) {
	if ( ! file_exists($messages_buffer_file) )
		touch($messages_buffer_file);

	$buffer = fopen($messages_buffer_file, "r+b");
	flock($buffer, LOCK_EX);
	$buffer_data = stream_get_contents($buffer);

	$messages = $buffer_data ? json_decode($buffer_data, true) : [];
	$next_id = (count($messages) > 0) ? $messages[count($messages) - 1]["id"] + 1 : 0;
	$messages[] = [ "id" => $next_id, "time" => time(), "name" => $_POST["name"], "content" => $_POST["content"] ];

	if (count($messages) > $messages_buffer_size)
		$messages = array_slice($messages, count($messages) - $messages_buffer_size);

	ftruncate($buffer, 0);
	rewind($buffer);
	fwrite($buffer, json_encode($messages, JSON_UNESCAPED_UNICODE));
	flock($buffer, LOCK_UN);
	fclose($buffer);

	if ($enable_chatlog)
		file_put_contents("chatlog.txt", date("Y-m-d H:i:s") . "\t" . strtr($_POST["name"], "\t", " ") . "\t" . strtr($_POST["content"], "\t", " ") . "\n", FILE_APPEND);

	exit();
}
?>
<!DOCTYPE html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Simple Chat</title>

<!-- Sağ üst köşe ses ayarı -->
<div style="position: fixed; top: 10px; right: 10px; z-index: 1000; display: flex; align-items: center; gap: 0.5em;">
	<input type="checkbox" id="soundToggle" checked>
	<h3><label for="soundToggle">Mesaj geldikçe ses çalsın</label></h3>
</div>

<script type="module">
	function setCookie(name, value, days) {
		const expires = new Date(Date.now() + days * 864e5).toUTCString();
		document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/';
	}

	function getCookie(name) {
		return document.cookie.split('; ').reduce((r, v) => {
			const parts = v.split('=');
			return parts[0] === name ? decodeURIComponent(parts[1]) : r
		}, '');
	}

	const soundToggle = document.getElementById("soundToggle");

	// Sayfa yüklenince cookie'den okuma
	const soundPref = getCookie("enableNotifSound");
	if (soundPref === "false") soundToggle.checked = false;
	else if (soundPref === "true") soundToggle.checked = true;

	// Değişiklik olunca cookie'ye yazma
	soundToggle.addEventListener("change", () => {
		setCookie("enableNotifSound", soundToggle.checked, 365);
	});

	document.querySelector("ul#messages > li").remove()

	document.querySelector("form").addEventListener("submit", async event => {
		const form = event.target
		const name = form.name.value
		const content = form.content.value

		event.preventDefault()

		if (name == "" || content == "")
			return

		await fetch(form.action, { method: "POST", body: new URLSearchParams({name, content}) })
		const messageList = document.querySelector("ul#messages")
		const messageElement = messageList.querySelector("template").content.cloneNode(true)
			messageElement.querySelector("span").textContent = name + ": " + content
		messageList.append(messageElement)

		messageList.scrollTop = messageList.scrollHeight
		form.content.value = ""
		form.content.focus()
	})

	async function poll_for_new_messages() {
		const response = await fetch("messages1.json", { cache: "no-cache" })
		if (!response.ok) return

		const messages = await response.json()
		const messageList = document.querySelector("ul#messages")
		const messageTemplate = messageList.querySelector("template").content.querySelector("li")

		const pixelDistance = messageList.scrollHeight - messageList.scrollTop - messageList.clientHeight
		const scrollToBottom = (pixelDistance < 50)

		for (const li of messageList.querySelectorAll("li.pending"))
			li.remove()

		const lastMessageId = parseInt(messageList.dataset.lastMessageId ?? "-1")

		for (const msg of messages) {
			if (msg.id > lastMessageId) {
				const messageElement = messageTemplate.cloneNode(true)
					messageElement.classList.remove("pending")
					messageElement.querySelector("span").textContent = msg.name + ": " + msg.content
				messageList.append(messageElement)
				messageList.dataset.lastMessageId = msg.id

				if (document.getElementById("soundToggle").checked) {
					const audio = new Audio("/chatphp/notif.mp3");
					audio.play();
				}
			}
		}

		for (const li of Array.from(messageList.querySelectorAll("li")).slice(0, -1000))
			li.remove()

		if (scrollToBottom)
			messageList.scrollTop = messageList.scrollHeight - messageList.clientHeight
	}

	poll_for_new_messages()
	setInterval(poll_for_new_messages, 2000)
</script>
<style>
	body {
		background-color: #000;
		color: #fff;
		font-family: Arial, sans-serif;
		margin: 0;
		padding: 2em;
		display: flex;
		flex-direction: column;
		gap: 1em;
		height: 100vh;
		box-sizing: border-box;
	}
	h1 {
		font-size: 2em;
		margin: 0 auto;
		text-align: center;
	}
	ul#messages {
		flex: 1 1 auto;
		overflow-y: auto;
		margin: 0 auto;
		padding: 0 1em;
		list-style: none;
		width: 100%;
		max-width: 100%;
		box-sizing: border-box;
		border: 1px solid gray;
	}
	ul#messages li {
		margin: 0.35em 0;
	}
	form {
		width: 100%;
		display: flex;
		justify-content: center;
		gap: 0.5em;
	}
	form input[name=name] {
		width: 20%;
	}
	form input[name=content] {
		flex-grow: 1;
	}
	form button {
	}
	#emoji-panel {
		background: #111;
		border: 1px solid #444;
		padding: 0.5em;
		display: none;
		position: fixed;
		bottom: 80px;
		right: 10px;
		z-index: 999;
		border-radius: 8px;
		box-shadow: 0 0 10px rgba(255, 255, 255, 0.1);
		width: 240px;
		max-height: 300px;
		overflow-y: auto;

		display: grid;
		grid-template-columns: repeat(5, 1fr);
		gap: 8px;
		text-align: center;
		font-size: 1.5em;
	}
	#emoji-panel span {
		cursor: pointer;
	}
</style>

<h1>Karşı atak Mesajlaşma Hizmeti</h1>

<ul id="messages" data-last-message-id="-1">
	<li>loading…</li>
	<template>
		<li class="pending">
			<span>...</span>
		</li>
	</template>
</ul>

<form method="post" action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8") ?>">
	<input type="text" name="name" placeholder="Name" value="Anonymous">
	<input type="text" name="content" placeholder="Message" id="contentInput" autofocus>
	<button type="submit">Send</button>
	<button type="button" id="emojiBtn">😀</button>
</form>

<div id="emoji-panel"></div>

<script>
	const emojiBtn = document.getElementById("emojiBtn");
	const emojiPanel = document.getElementById("emoji-panel");
	const contentInput = document.getElementById("contentInput");

	const emojiList = "😀😁😂🤣😃😄😅😆😉😊😋😎😍😘😗😙😚🙂🤗🤩🤔🤨😐😑😶🙄😏😣😥😮🤐😯😪😫🥱😴😌😛😜😝🤤😒😓😔😕🙃🤑😲☹️🙁😖😞😟😤😢😭😦😧😨😩🤯😬😰😱🥵🥶😳🤪😵😡😠🤬😷🤒🤕🤢🤮🥴😇🤠🥳🥸😈👿👹👺💀👻👽🤖🎃😺😸😹😻😼😽🙀😿😾🐶🐱🐭🐹🐰🦊🐻🐼🐨🐯🦁🐮🐷🐽🐸🐵🙈🙉🙊🐒🦄🐔🐧🐦🐤🐣🐥🦆🦅🦉🦇🐺🐗🐴🦓🦍🦧🦣🐘🦛🦏🐪🐫🦒🦘🦬🐃🐂🐄🐎🐖🐏🐑🐐🦌🐕🐩🦮🐕‍🦺🐈🐈‍⬛🪶🐓🦃🦤🦚🦜🦢🦩🐇🦝🦨🦡🦫🦦🦥🐁🐀🐿️🦔🐉🐲";

	Array.from(emojiList).forEach(emoji => {
		const span = document.createElement("span");
		span.textContent = emoji;
		span.addEventListener("click", () => {
			contentInput.value += emoji;
			contentInput.focus();
		});
		emojiPanel.appendChild(span);
	});

	emojiBtn.addEventListener("click", () => {
		emojiPanel.style.display = emojiPanel.style.display === "grid" ? "none" : "grid";
	});
</script>
