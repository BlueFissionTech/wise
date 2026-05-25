#!jenss

use @system, @io from system;

speak via @io: $default;

say "Chatbot profile setup.";

$tone
$tone with "Tone (neutral, friendly, concise, analytical):"?

$persona
$persona with "Persona focus (engineering, administration, productivity, creativity):"?

$reminders
$reminders with "Reminder frequency (low, medium, high):"?

say "Profile summary:";
say "Tone: ` $tone `";
say "Persona: ` $persona `";
say "Reminders: ` $reminders `";
