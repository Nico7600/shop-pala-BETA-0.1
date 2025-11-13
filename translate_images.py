import os
from PIL import Image
import pytesseract
from googletrans import Translator

# Chemin du dossier images
images_dir = os.path.join(os.path.dirname(__file__), "images")
output_dir = os.path.join(os.path.dirname(__file__), "images_translated")
os.makedirs(output_dir, exist_ok=True)

translator = Translator()

for filename in os.listdir(images_dir):
    if filename.lower().endswith(('.png', '.jpg', '.jpeg', '.bmp', '.gif')):
        img_path = os.path.join(images_dir, filename)
        img = Image.open(img_path)
        text = pytesseract.image_to_string(img, lang='eng')
        translated = translator.translate(text, dest='fr').text

        output_path = os.path.join(output_dir, f"{os.path.splitext(filename)[0]}_fr.txt")
        with open(output_path, "w", encoding="utf-8") as f:
            f.write(translated)
