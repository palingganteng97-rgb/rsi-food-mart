<?php
/**
 * Generate a simple notification sound as a valid MP3 file.
 * This creates a minimal beep sound using raw PCM data wrapped in WAV format.
 * Run this file once from CLI: php assets/generate_sound.php
 */

$filename = __DIR__ . '/notification.mp3';

// Since we can't easily generate MP3 in pure PHP, we'll create a WAV file instead
// and the browser can play it fine. Rename as .mp3 or just use .wav
// Actually, let's check what's the best approach.

// We'll create a minimal valid WAV file with a simple sine wave beep
// Most browsers can play .wav

$sampleRate = 44100;
$duration = 0.5; // 0.5 seconds
$frequency = 880; // A5 note - pleasant notification sound

$numSamples = $sampleRate * $duration;
$dataSize = $numSamples * 2; // 16-bit samples (2 bytes each)

// WAV Header
$header = '';
$header .= 'RIFF'; // ChunkID
$header .= pack('V', 36 + $dataSize); // ChunkSize
$header .= 'WAVE'; // Format
$header .= 'fmt '; // Subchunk1ID
$header .= pack('V', 16); // Subchunk1Size (16 for PCM)
$header .= pack('v', 1); // AudioFormat (1 = PCM)
$header .= pack('v', 1); // NumChannels (1 = mono)
$header .= pack('V', $sampleRate); // SampleRate
$header .= pack('V', $sampleRate * 2); // ByteRate
$header .= pack('v', 2); // BlockAlign
$header .= pack('v', 16); // BitsPerSample
$header .= 'data'; // Subchunk2ID
$header .= pack('V', $dataSize); // Subchunk2Size

// Generate sine wave samples with envelope (fade in/out)
$data = '';
for ($i = 0; $i < $numSamples; $i++) {
    $t = $i / $sampleRate;
    
    // Envelope: fade in (0 to 0.05s) and fade out (0.35s to 0.5s)
    $envelope = 1.0;
    if ($t < 0.02) {
        $envelope = $t / 0.02; // fade in
    } elseif ($t > ($duration - 0.02)) {
        $envelope = ($duration - $t) / 0.02; // fade out
    }
    
    // Generate sample with some harmonics for richer sound
    $sample = sin(2 * M_PI * $frequency * $t) * 0.6
            + sin(2 * M_PI * $frequency * 2 * $t) * 0.2
            + sin(2 * M_PI * $frequency * 3 * $t) * 0.1;
    
    $sample *= $envelope * 0.8; // Master volume
    
    // Convert to 16-bit PCM
    $intSample = (int)($sample * 32767);
    $intSample = max(-32768, min(32767, $intSample));
    
    $data .= pack('v', $intSample & 0xFFFF);
}

// Write WAV file
$wavFilename = __DIR__ . '/notification.wav';
file_put_contents($wavFilename, $header . $data);

echo "Generated: $wavFilename\n";
echo "Size: " . filesize($wavFilename) . " bytes\n";

// Also create an mp3 placeholder (just copy the wav for now - browsers play both)
// In production, replace with a real notification.mp3
echo "\nSound file created at assets/notification.wav\n";
echo "To use .mp3 instead, replace with a real notification.mp3 file.\n";
echo "The JavaScript will try notification.mp3 first, then fall back to .wav\n";
?>

