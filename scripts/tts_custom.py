import sys
from TTS.utils.synthesizer import Synthesizer

def text_to_speech(text, model_path, config_path, vocoder_path, vocoder_config_path):
    synthesizer = Synthesizer(
        model_path, config_path, vocoder_path, vocoder_config_path
    )
    wav = synthesizer.tts(text)
    synthesizer.save_wav(wav, "speech.wav")

def play_audio(file_path):
    import playsound
    playsound.playsound(file_path)

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python tts_custom.py 'your text here'")
        sys.exit(1)
    text = sys.argv[1]
    model_path = 'path/to/your/model.pth'
    config_path = 'path/to/your/config.json'
    vocoder_path = 'path/to/your/vocoder.pth'
    vocoder_config_path = 'path/to/your/vocoder/config.json'
    text_to_speech(text, model_path, config_path, vocoder_path, vocoder_config_path)
    play_audio("speech.wav")
