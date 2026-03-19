import re

file_path = '/Users/thomas/redaxo_instances/core/project/public/redaxo/src/addons/geolocation/assets/demo.js'

with open(file_path, 'r') as f:
    code = f.read()

# wir wollen ein allgemeines overlay in `initDemoMaps` machen, oder wir machen es in CSS + HTML:
# Eigentlich einfacher: die interaktion wieder normal machen und ein html-div darüberlegen.
