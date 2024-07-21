import pyttsx3
import sys

def get_voice(engine, desired_voice):
    voices = engine.getProperty('voices')
    for voice in voices:
        if desired_voice.lower() in voice.name.lower():
            return voice.id
    return None

def text_to_speech(text, desired_voice=""):
    engine = pyttsx3.init()
    if desired_voice:
        voice_id = get_voice(engine, desired_voice)
        if voice_id:
            engine.setProperty('voice', voice_id)
        else:
            print(f"Desired voice '{desired_voice}' not found. Using default voice.")
    engine.say(text)
    engine.runAndWait()

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python tts_pyttsx3.py 'your text here' [desired_voice]")
        sys.exit(1)
    text = sys.argv[1]
    desired_voice = sys.argv[2] if len(sys.argv) > 2 else ""
    text_to_speech(text, desired_voice)
