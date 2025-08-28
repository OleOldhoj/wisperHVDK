<?php
namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../public_html/openai_transcribe.php';

class PrepareAudioForOpenAiTest extends TestCase
{
    public function testUsesOriginalWhenSmall(): void
    {
        $wav = tempnam(sys_get_temp_dir(), 'wisper') . '.wav';
        file_put_contents($wav, str_repeat('a', 10));
        putenv('OPENAI_MAX_CONTENT=1000');
        [$path, $mime] = prepare_audio_for_openai($wav);
        $this->assertSame($wav, $path);
        $this->assertSame('audio/wav', $mime);
        unlink($wav);
    }

    public function testConvertsLargeWavToMp3(): void
    {
        $wav = tempnam(sys_get_temp_dir(), 'wisper') . '.wav';
        file_put_contents($wav, str_repeat('a', 600));

        $ffmpeg = tempnam(sys_get_temp_dir(), 'ffmpeg');
        file_put_contents($ffmpeg, "#!/bin/sh\n" .
            "in=''\n" .
            "while [ $# -gt 0 ]; do\n" .
            "  if [ \"$1\" = '-i' ]; then in=$2; shift 2; continue; fi\n" .
            "  out=$1; shift;\n" .
            "done\n" .
            "cp \"$in\" \"$out\"\n");
        chmod($ffmpeg, 0755);
        putenv('FFMPEG_BIN=' . $ffmpeg);
        putenv('OPENAI_MAX_CONTENT=1000');

        [$path, $mime, $tmp] = prepare_audio_for_openai($wav);
        $this->assertNotSame($wav, $path);
        $this->assertSame('audio/mpeg', $mime);
        $this->assertFileExists($path);

        if ($tmp !== null && file_exists($tmp)) {
            unlink($tmp);
        }
        unlink($wav);
        unlink($ffmpeg);
    }
}
