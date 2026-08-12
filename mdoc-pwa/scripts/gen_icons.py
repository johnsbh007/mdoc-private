"""Generate mDoc PWA icons (192/512) and favicon from a simple teal document design."""
from PIL import Image, ImageDraw, ImageFont

SIZE = 512
img = Image.new("RGBA", (SIZE, SIZE), (15, 118, 110, 255))  # teal-700 #0f766e
d = ImageDraw.Draw(img)

# Simple document shape (white rounded rect with folded corner)
margin = 96
doc_w = SIZE - 2 * margin
doc_h = SIZE - 2 * margin - 30
d.rounded_rectangle([margin, margin, margin + doc_w, margin + doc_h], radius=28, fill="white")

# Folded corner triangle
d.polygon(
    [(margin + doc_w - 90, margin), (margin + doc_w - 90, margin + 90), (margin + doc_w, margin + 90)],
    fill=(15, 118, 110, 255),
)
d.polygon(
    [(margin + doc_w - 90, margin), (margin + doc_w, margin + 90)],
    fill=(226, 232, 240, 255),  # slate-200 fold edge
)

# Text lines (suggest document content)
line_w = doc_w - 80
line_h = 22
line_y = margin + 70
for i in range(3):
    d.rounded_rectangle([margin + 40, line_y + i * (line_h + 26), margin + 40 + line_w - (i * 60), line_y + i * (line_h + 26) + line_h], radius=11, fill=(100, 116, 139, 255))
    line_y += 26

# Check mark (approved)
d.ellipse([margin + 40, line_y + 40, margin + 40 + 110, line_y + 40 + 110], fill=(15, 118, 110, 255))
d.line([margin + 60, line_y + 96, margin + 82, line_y + 118], fill="white", width=16)
d.line([margin + 82, line_y + 118, margin + 130, line_y + 62], fill="white", width=16)

img.save("public/icons/icon-512.png")
img.resize((192, 192), Image.LANCZOS).save("public/icons/icon-192.png")
img.resize((64, 64), Image.LANCZOS).save("public/favicon.png")
print("icons generated")
