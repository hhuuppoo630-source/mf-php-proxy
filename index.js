const express = require('express');
const app = express();

// دالة لتحديد رابط الفيديو حسب رقم الحلقة
function getStreamUrl(episodeId) {
    // روابط M3U8 تجريبية للتجربة (يمكنك استبدالها بروابط Dailymotion المباشرة)
    const episodes = {
        'e1': 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
        'e2': 'https://bitdash-a.akamaihd.net/content/sintel/hls/playlist.m3u8'
    };
    
    return episodes[episodeId] || episodes['e1'];
}

// المسار الذي يفتحه المستخدم
app.get('/watch/:episode', (req, res) => {
    const episode = req.params.episode;
    const streamUrl = getStreamUrl(episode);

    // إرجاع صفحة HTML تحتوي على المشغل دون إظهار الرابط في العنوان
    res.send(`
      <!DOCTYPE html>
      <html lang="ar" dir="rtl">
      <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>مشاهدة الحلقة ${episode}</title>
        <link href="https://vjs.zencdn.net/7.20.3/video-js.css" rel="stylesheet" />
        <style>
          body { margin: 0; background-color: #0d1117; display: flex; justify-content: center; align-items: center; height: 100vh; }
          .video-container { width: 90%; max-width: 900px; aspect-ratio: 16/9; }
        </style>
      </head>
      <body>
        <div class="video-container">
          <video id="my-video" class="video-js vjs-default-skin vjs-big-play-centered" controls autoplay preload="auto" width="100%" height="100%">
            <source src="${streamUrl}" type="application/x-mpegURL">
          </video>
        </div>
        <script src="https://vjs.zencdn.net/7.20.3/video.min.js"></script>
      </body>
      </html>
    `);
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => console.log(`Server running on port ${PORT}`));
