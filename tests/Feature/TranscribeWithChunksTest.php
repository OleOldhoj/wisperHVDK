<?php
namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../public_html/openai_transcribe.php';

class TranscribeWithChunksTest extends TestCase
{
    public function testNoChunkForSmallFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wisper') . '.wav';
        file_put_contents($file, str_repeat('a', 10));
        putenv('OPENAI_MAX_CONTENT=1000');
        putenv('OPENAI_MAX_DURATION=1000');
        $calls = 0;
        $result = transcribe_with_chunks($file, function (string $path) use (&$calls) {
            $calls++;
            return 'ok';
        });
        $this->assertSame('ok', $result);
        $this->assertSame(1, $calls);
        unlink($file);
        putenv('OPENAI_MAX_CONTENT');
        putenv('OPENAI_MAX_DURATION');
    }

    public function testChunksLargeFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wisper') . '.wav';
        file_put_contents($file, str_repeat('a', 2000));

        $ffmpeg = tempnam(sys_get_temp_dir(), 'ffmpeg');
        file_put_contents($ffmpeg, "#!/bin/sh\n" .
            "in=''\n" .
            "while [ $# -gt 0 ]; do\n" .
            "  if [ \"$1\" = '-i' ]; then in=$2; shift 2; continue; fi\n" .
            "  out=$1; shift;\n" .
            "done\n" .
            "out0=$(printf \"$out\" 0)\n" .
            "out1=$(printf \"$out\" 1)\n" .
            "cp \"$in\" \"$out0\"\n" .
            "cp \"$in\" \"$out1\"\n");
        chmod($ffmpeg, 0755);
        putenv('FFMPEG_BIN=' . $ffmpeg);
        putenv('OPENAI_MAX_CONTENT=1000');
        putenv('OPENAI_MAX_DURATION=1000');

        $calls = 0;
        $result = transcribe_with_chunks($file, function (string $path) use (&$calls) {
            $calls++;
            return 'chunk';
        });
        $this->assertSame("chunk\r\nchunk", $result);
        $this->assertSame(2, $calls);

        unlink($file);
        unlink($ffmpeg);
        putenv('FFMPEG_BIN');
        putenv('OPENAI_MAX_CONTENT');
        putenv('OPENAI_MAX_DURATION');
    }

    public function testChunksLongRecordingByDuration(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'wisper') . '.wav';
        file_put_contents($file, str_repeat('a', 16000));

        $ffmpeg = tempnam(sys_get_temp_dir(), 'ffmpeg');
        file_put_contents($ffmpeg, "#!/bin/sh\n" .
            "in=''\n" .
            "while [ $# -gt 0 ]; do\n" .
            "  if [ \"$1\" = '-i' ]; then in=$2; shift 2; continue; fi\n" .
            "  out=$1; shift;\n" .
            "done\n" .
            "out0=$(printf \"$out\" 0)\n" .
            "out1=$(printf \"$out\" 1)\n" .
            "cp \"$in\" \"$out0\"\n" .
            "cp \"$in\" \"$out1\"\n");
        chmod($ffmpeg, 0755);
        putenv('FFMPEG_BIN=' . $ffmpeg);
        putenv('OPENAI_MAX_CONTENT=100000');
        putenv('OPENAI_MAX_DURATION=1');

        $calls = 0;
        $result = transcribe_with_chunks($file, function (string $path) use (&$calls) {
            $calls++;
            return 'part';
        });
        $this->assertSame("part\r\npart", $result);
        $this->assertSame(2, $calls);

        unlink($file);
        unlink($ffmpeg);
        putenv('FFMPEG_BIN');
        putenv('OPENAI_MAX_CONTENT');
        putenv('OPENAI_MAX_DURATION');
    }
}
