import numpy as np
import simpleaudio as sa
import sys

dtmf_freqs = {
    '1': [697, 1209],
    '2': [697, 1336],
    '3': [697, 1477],
    '4': [770, 1209],
    '5': [770, 1336],
    '6': [770, 1477],
    '7': [852, 1209],
    '8': [852, 1336],
    '9': [852, 1477],
    '0': [941, 1336],
    '*': [941, 1209],
    '#': [941, 1477],
    'A': [697, 1633],
    'B': [770, 1633],
    'C': [852, 1633],
    'D': [941, 1633],
}

def generate_tone(digit, duration=0.5, sample_rate=44100):
    if digit not in dtmf_freqs:
        raise ValueError(f"Invalid DTMF digit: {digit}")
    f1, f2 = dtmf_freqs[digit]
    t = np.linspace(0, duration, int(sample_rate * duration), endpoint=False)
    tone = np.sin(2 * np.pi * f1 * t) + np.sin(2 * np.pi * f2 * t)
    tone *= 32767 / np.max(np.abs(tone))
    return tone.astype(np.int16)

def play_tone(tone, sample_rate=44100):
    play_obj = sa.play_buffer(tone, 1, 2, sample_rate)
    play_obj.wait_done()

if __name__ == "__main__":
    digit = sys.argv[1]
    duration = float(sys.argv[2]) if len(sys.argv) > 2 else 0.5
    tone = generate_tone(digit, duration)
    play_tone(tone)
