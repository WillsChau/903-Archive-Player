<?php
/**
 * Local Radio Archive Player - PHP Version
 * Acts as both the server (handling API) and the UI frontend.
 */

// 1. API Logic
if (isset($_GET['api']) && $_GET['api'] === 'recordings') {
    header('Content-Type: application/json');

    // Serve existing content
    $json_file = __DIR__ . '/recordings.json';
    if (file_exists($json_file)) {
        echo file_get_contents($json_file);
    } else {
        echo json_encode([]);
    }
    exit;
}

// 2. UI Logic (Modified index.html)
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>903 Local Archive (PHP)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body {
            background-color: #000000 !important;
            color: #ffffff;
        }

        .border-gray {
            border: 1px solid #333333;
        }

        .bg-player {
            background-color: #000000;
        }

        .recording-card:hover {
            border-color: #ffffff;
        }

        input[type="range"] {
            -webkit-appearance: none;
            appearance: none;
            background: #333333;
            border-radius: 999px;
            height: 4px;
        }

        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 12px;
            width: 12px;
            border-radius: 50%;
            background: #ffffff;
            cursor: pointer;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #000000;
        }

        ::-webkit-scrollbar-thumb {
            background: #333333;
            border-radius: 4px;
        }
    </style>
</head>

<body class="bg-black text-white min-h-screen p-4 sm:p-8">
    <div class="max-w-4xl mx-auto">
        <header class="mb-12 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl sm:text-4xl font-bold mb-1 sm:mb-2 text-white">叱咤903 Archive <span
                        class="text-xs font-normal opacity-50">PHP</span></h1>
                <p class="text-xs sm:text-sm text-zinc-500">Local Player & Library</p>
            </div>
            <div class="flex items-center gap-3">
                <select id="show-filter" onchange="filterRecordings()"
                    class="bg-zinc-900 border border-zinc-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2 text-white">
                    <option value="">All Shows</option>
                    <option value="Bad Girl大過佬">Bad Girl大過佬</option>
                    <option value="在晴朗的一天出發">在晴朗的一天出發</option>
                    <option value="聖艾粒LaLaLaLa">聖艾粒LaLaLaLa</option>
                </select>
                <div onclick="loadRecordings()"
                    class="p-3 rounded-full border border-zinc-800 cursor-pointer hover:border-white transition-all group active:scale-95 flex-shrink-0"
                    title="Refresh Library">
                    <i data-lucide="refresh-cw" size="20"
                        class="text-zinc-500 group-hover:text-white transition-colors"></i>
                </div>
            </div>
        </header>

        <section id="now-playing" class="mb-8 sm:mb-12 hidden">
            <div class="border-gray p-4 sm:p-8 rounded-xl bg-player relative overflow-hidden">
                <h2 class="text-[10px] sm:text-xs font-semibold tracking-widest text-zinc-500 uppercase mb-3 sm:mb-4">
                    Now Playing</h2>
                <h3 id="current-title" class="text-lg sm:text-2xl font-bold mb-6 sm:mb-8 truncate max-w-full">Show Title
                </h3>

                <audio id="audio-player" class="w-100 hidden" controls></audio>

                <div class="flex flex-col gap-6">
                    <div
                        class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 bg-zinc-950 p-4 rounded-xl border border-zinc-800">
                        <div class="flex items-center gap-4 flex-grow">
                            <button id="play-pause" onclick="togglePlay()"
                                class="text-white hover:opacity-80 transition-all p-3 sm:p-2 bg-zinc-900 sm:bg-transparent rounded-full sm:rounded-none">
                                <i data-lucide="play" fill="currentColor"></i>
                            </button>

                            <span id="current-time"
                                class="text-[10px] font-mono text-zinc-500 min-w-[35px] sm:min-w-[40px]">00:00</span>

                            <div class="flex-grow relative h-8 sm:h-6 flex items-center">
                                <input type="range" id="seek-bar" value="0" min="0" max="100" step="0.1"
                                    class="w-full cursor-pointer accent-white" oninput="onSeekInput()"
                                    onchange="onSeekChange()">
                            </div>

                            <span id="total-duration"
                                class="text-[10px] font-mono text-zinc-500 min-w-[35px] sm:min-w-[40px]">00:00</span>
                        </div>

                        <div
                            class="flex items-center justify-end gap-3 sm:ml-2 sm:border-l border-zinc-900 sm:pl-4 pt-2 sm:pt-0 border-t sm:border-t-0 border-zinc-900 sm:border-none">
                            <button id="mute-btn" onclick="toggleMute()"
                                class="text-zinc-500 hover:text-white transition-colors p-2">
                                <i data-lucide="volume-2" size="20" class="sm:hidden"></i>
                                <i data-lucide="volume-2" size="18" class="hidden sm:block"></i>
                            </button>
                            <input type="range" id="volume-bar" value="1" min="0" max="1" step="0.01"
                                class="w-full sm:w-20 h-2 sm:h-1 accent-white cursor-pointer" oninput="onVolumeInput()">
                        </div>
                    </div>

                    <div class="flex items-center gap-3 sm:gap-4">
                        <button onclick="skip(-30)"
                            class="flex-grow sm:flex-grow-0 flex items-center justify-center gap-1.5 text-zinc-400 hover:text-white transition-colors text-xs border border-zinc-800 px-6 py-3 sm:px-4 sm:py-2 rounded-md">
                            <i data-lucide="rewind" size="16" class="sm:hidden"></i>
                            <i data-lucide="rewind" size="14" class="hidden sm:block"></i> -30s
                        </button>
                        <button onclick="skip(30)"
                            class="flex-grow sm:flex-grow-0 flex items-center justify-center gap-1.5 text-zinc-400 hover:text-white transition-colors text-xs border border-zinc-800 px-6 py-3 sm:px-4 sm:py-2 rounded-md">
                            <i data-lucide="fast-forward" size="16" class="sm:hidden"></i>
                            <i data-lucide="fast-forward" size="14" class="hidden sm:block"></i> +30s
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold flex items-center gap-2">
                    <i data-lucide="library" size="20"></i>
                    Archive Library
                </h2>
                <div id="refresh-status"
                    class="text-xs text-zinc-600 flex items-center gap-2 opacity-0 transition-opacity">
                    <span class="w-1.5 h-1.5 bg-zinc-600 rounded-full animate-pulse"></span>
                </div>
            </div>
            <div id="recordings-list" class="grid gap-3 sm:gap-4">
                <div class="border-gray p-4 sm:p-6 rounded-xl flex items-center gap-4 animate-pulse">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-zinc-900 rounded"></div>
                    <div class="space-y-2">
                        <div class="w-32 sm:w-48 h-4 bg-zinc-900 rounded"></div>
                        <div class="w-20 sm:w-24 h-3 bg-zinc-900 rounded"></div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        lucide.createIcons();

        const player = document.getElementById('audio-player');
        const playBtn = document.getElementById('play-pause');
        const nowPlaying = document.getElementById('now-playing');
        const currentTitle = document.getElementById('current-title');
        const seekBar = document.getElementById('seek-bar');
        const volumeBar = document.getElementById('volume-bar');
        const curTimeText = document.getElementById('current-time');
        const totalDurationText = document.getElementById('total-duration');
        const muteBtn = document.getElementById('mute-btn');

        let isDragging = false;
        let allFiles = []; // Store all recordings globally for filtering

        function filterRecordings() {
            const filterValue = document.getElementById('show-filter').value;
            if (filterValue === "") {
                renderList(allFiles); // Show all
            } else {
                const filtered = allFiles.filter(f => f.url.includes(encodeURIComponent(filterValue)));
                renderList(filtered);
            }
        }

        function togglePlay() {
            if (player.paused) player.play();
            else player.pause();
        }

        function toggleMute() {
            player.muted = !player.muted;
            updateVolumeIcon();
        }

        function updateVolumeIcon() {
            if (player.muted || player.volume === 0) {
                muteBtn.innerHTML = '<i data-lucide="volume-x" size="18" class="sm:hidden"></i><i data-lucide="volume-x" size="16" class="hidden sm:block"></i>';
            } else if (player.volume < 0.5) {
                muteBtn.innerHTML = '<i data-lucide="volume-1" size="18" class="sm:hidden"></i><i data-lucide="volume-1" size="16" class="hidden sm:block"></i>';
            } else {
                muteBtn.innerHTML = '<i data-lucide="volume-2" size="20" class="sm:hidden"></i><i data-lucide="volume-2" size="18" class="hidden sm:block"></i>';
            }
            lucide.createIcons();
        }

        player.onplay = () => {
            playBtn.innerHTML = '<i data-lucide="pause" fill="currentColor"></i>';
            lucide.createIcons();
        };
        player.onpause = () => {
            playBtn.innerHTML = '<i data-lucide="play" fill="currentColor"></i>';
            lucide.createIcons();
        };

        player.ontimeupdate = () => {
            if (!isDragging) {
                curTimeText.innerText = formatTime(player.currentTime);
                totalDurationText.innerText = formatTime(player.duration);
                seekBar.max = player.duration || 100;
                seekBar.value = player.currentTime;
            }

            if (currentTitle.innerText !== "Show Title") {
                localStorage.setItem('lastPlayedFile', currentTitle.innerText);
                localStorage.setItem('lastPlayedTime', player.currentTime);
            }
        };

        function onSeekInput() {
            isDragging = true;
            curTimeText.innerText = formatTime(seekBar.value);
        }

        function onSeekChange() {
            player.currentTime = seekBar.value;
            isDragging = false;
        }

        function onVolumeInput() {
            player.volume = volumeBar.value;
            player.muted = false;
            updateVolumeIcon();
        }

        function formatTime(s) {
            if (!s || isNaN(s)) return "00:00";
            const hr = Math.floor(s / 3600);
            const min = Math.floor((s % 3600) / 60);
            const sec = Math.floor(s % 60);
            if (hr > 0) return `${hr}:${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
            return `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
        }

        function skip(s) {
            if (player.readyState >= 1) {
                const newTime = player.currentTime + s;
                player.currentTime = Math.max(0, Math.min(newTime, player.duration || Infinity));
            }
        }

        async function loadRecordings(isAutoFollowUp = false) {
            const refreshStatus = document.getElementById('refresh-status');
            refreshStatus.classList.remove('opacity-0');

            try {
                let data;
                if (!isAutoFollowUp) {
                    // Manual refresh: perform synchronous update and get results back
                    const syncResponse = await fetch('scraper.php?sync=1');
                    data = await syncResponse.json();
                } else {
                    // Auto-followup: just fetch current JSON
                    const response = await fetch('?api=recordings');
                    data = await response.json();
                }

                allFiles = data;
                filterRecordings();

                setTimeout(() => refreshStatus.classList.add('opacity-0'), 1000);
            } catch (e) {
                console.error("Failed to load recordings", e);
                renderList([]);
                if (refreshStatus) refreshStatus.classList.add('opacity-0');
            }

            const lastFile = localStorage.getItem('lastPlayedFile');
            const lastTime = localStorage.getItem('lastPlayedTime');

            if (lastFile && !isAutoFollowUp) {
                const found = allFiles.find(f => f.title === lastFile);
                if (found) playFile(found.title, found.url, parseFloat(lastTime), false);
            }


        }

        function renderList(files) {
            const list = document.getElementById('recordings-list');
            if (files.length === 0) {
                list.innerHTML = '<p class="text-zinc-500">No recordings found.</p>';
                return;
            }
            list.innerHTML = files.map(f => `
                <div class="recording-card border-gray p-4 sm:p-6 rounded-xl flex items-center gap-3 sm:gap-4 cursor-pointer transition-all" onclick="playFile('${f.title}', '${f.url}')">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-zinc-900 rounded flex items-center justify-center flex-shrink-0">
                        <i data-lucide="music-2" class="text-zinc-600" size="18"></i>
                    </div>
                    <div class="flex-grow min-w-0">
                        <h4 class="font-semibold text-base sm:text-lg mb-1 truncate">${f.title}</h4>
                        <div class="flex gap-3 sm:gap-4 text-[10px] sm:text-xs text-zinc-600">
                             <span class="flex items-center gap-1 text-blue-400 font-medium">${extractShowNameFromUrl(f.url)}</span>
                             <span class="flex items-center gap-1"><i data-lucide="globe" size="10"></i> Remote</span>
                             <span class="flex items-center gap-1"><i data-lucide="file-audio" size="10"></i> AAC</span>
                        </div>
                    </div>
                    <i data-lucide="play-circle" class="text-zinc-800 hover:text-white transition-colors flex-shrink-0"></i>
                </div>
            `).join('');
            lucide.createIcons();
        }

        function playFile(title, url, startTime = 0, shouldPlay = true) {
            nowPlaying.classList.remove('hidden');
            currentTitle.innerText = title;

            const onDataLoaded = () => {
                if (startTime > 0 && !isNaN(startTime)) {
                    const attemptSeek = () => {
                        if (player.readyState >= 1) { // HAVE_METADATA
                            player.currentTime = startTime;
                        } else {
                            setTimeout(attemptSeek, 100);
                        }
                    };
                    attemptSeek();
                }

                if (shouldPlay) {
                    player.play().catch(e => {
                        console.error("Play error:", e);
                        playBtn.innerHTML = '<i data-lucide="play" fill="currentColor"></i>';
                        lucide.createIcons();
                    });
                }
                player.removeEventListener('loadeddata', onDataLoaded);
            };

            player.addEventListener('loadeddata', onDataLoaded);
            player.src = url;
            player.load();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function extractShowNameFromUrl(url) {
            if (url.includes('Bad%20Girl%E5%A4%A7%E9%81%8E%E4%BD%AC')) return 'Bad Girl大過佬';
            if (url.includes('%E5%9C%A8%E6%99%B4%E6%9C%97%E7%9A%84%E4%B8%80%E5%A4%A9%E5%87%BA%E7%99%BC')) return '在晴朗的一天出發';
            if (url.includes('%E8%81%96%E8%89%BE%E7%B2%92LaLaLaLa')) return '聖艾粒LaLaLaLa';
            return 'Unknown Show';
        }

        loadRecordings();
    </script>
</body>

</html>