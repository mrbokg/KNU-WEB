# Промпты для фото товаров (ChatGPT, генерация изображений)

## Как делать

1. Открой **один** чат в ChatGPT и делай все картинки в нём, чтобы стиль совпадал.
2. Первым сообщением прикрепи логотипы (папка `theme/knu-merch/assets/img/`): `logo-white.png`, `logo-blue.png`, `logo.png`. Вставь БАЗОВЫЙ СТИЛЬ, затем промпт №1.
3. Дальше отправляй промпты по очереди. Если стиль поплыл, напиши «same style, lighting and background as the first image».
4. Сохраняй файлы в папку `Сайт КНУ/incoming/` под указанными именами (формат png или jpg, не важен). Обрезку, размер и WebP я сделаю сам.
5. Логотип ИИ может исказить. Приоритет — вещь и свет: если логотип вышел криво, но фото хорошее, всё равно сохраняй, логотип наложу сам.

## БАЗОВЫЙ СТИЛЬ (вставить один раз в начале)

```
You are creating a consistent e-commerce product photo series for a minimalist university merch store (Kyrgyz National University, collection "1925").
Style for EVERY image: professional studio product photography, ghost-mannequin / neatly arranged flat lay, garment centered, soft diffused daylight from the upper left, very soft natural shadow, seamless plain background in warm light grey #F5F5F3, no props, no people, no text other than what I specify, photorealistic heavyweight cotton fleece with visible knit texture, clean premium look like COS / Uniqlo U / Harvard Shop catalogue.
Logo: use the attached round KNU seal logo. On garments it is a small direct EMBROIDERY (no patch, thread stitched into the fabric) on the LEFT chest (wearer's left, viewer's right), about 8–9 cm wide — white thread on navy and black items, blue thread (#0067AC) on grey items. Keep the logo round and undistorted.
Aspect ratio for product images: vertical 2:3 (portrait), item fills ~75% of the frame.
```

## Промпты

**1. `set-hoodie-navy-1`**
```
Navy blue (#1B2A44) heavyweight cotton pullover hoodie, front view, ghost mannequin, kangaroo pocket, flat drawstrings, ribbed cuffs and hem, white embroidered KNU logo on the left chest. Portrait 2:3.
```

**2. `set-hoodie-navy-2`**
```
Matching set flat lay: the same navy hoodie (#1B2A44) and matching navy jogger sweatpants with ribbed cuffs and drawstring waist, arranged neatly side by side (hoodie upper left, joggers lower right, slightly overlapping). Small white embroidered text "КНУ · 1925" on the left thigh of the joggers. Same lighting and background. Portrait 2:3.
```

**3. `set-hoodie-grey-1`** — как №1, но:
```
Same as image 1 but in heather grey melange (#B9B9B6) with the logo embroidered in blue thread #0067AC.
```

**4. `set-hoodie-grey-2`**
```
Same composition as image 2, heather grey melange set, blue embroidery (#0067AC), "КНУ · 1925" in blue thread on the joggers.
```

**5. `set-hoodie-black-1`**
```
Same as image 1 but in black (#141414), white embroidery.
```

**6. `set-hoodie-black-2`**
```
Same composition as image 2, black set, white embroidery.
```

**7. `set-sweatshirt-navy-1`**
```
Navy (#1B2A44) heavyweight cotton crewneck sweatshirt (no hood, no pocket), front view, ghost mannequin, ribbed collar, cuffs and hem, white embroidered KNU logo on the left chest. Portrait 2:3.
```

**8. `set-sweatshirt-navy-2`**
```
Flat lay of the navy crewneck sweatshirt and matching navy joggers side by side, same arrangement and lighting as image 2, "КНУ · 1925" small white embroidery on the left thigh. Portrait 2:3.
```

**9. `set-sweatshirt-grey-1`**
```
Same as image 7 in heather grey melange (#B9B9B6), blue embroidery #0067AC.
```

**10. `set-sweatshirt-grey-2`**
```
Same as image 8 in heather grey melange, blue embroidery.
```

**11. `set-sweatshirt-black-1`**
```
Same as image 7 in black (#141414), white embroidery.
```

**12. `set-sweatshirt-black-2`**
```
Same as image 8 in black, white embroidery.
```

**13. `cap-navy-1`**
```
Navy (#1B2A44) six-panel cotton baseball cap, three-quarter front view, curved brim, white embroidered KNU logo centered on the front panels. Same background and light. Portrait 2:3, cap centered with generous empty space.
```

**14. `cap-black-1`**
```
Same as image 13 in black (#141414), white embroidery.
```

**15. `mug-white-1`**
```
White glossy ceramic mug, 330 ml, three-quarter view with handle on the right, full-color KNU logo printed on the front. Same background and light. Portrait 2:3, mug centered with generous empty space.
```

**16. `keychain-blue-1`**
```
Round enamel metal keychain (about 4 cm) with the full-color KNU logo, polished silver metal ring and short chain, lying on the background, slight top-down angle. Same background and light. Portrait 2:3, object small and centered with lots of empty space.
```

**17. `hero`** (главный баннер)
```
Wide hero image, landscape 3:2: premium flat lay of the full collection on the #F5F5F3 background — navy hoodie in the center, folded heather grey sweatshirt on the left, black joggers on the right, navy cap and the white KNU mug at the bottom, all with the KNU logo embroidered/printed as described. Calm editorial composition with generous negative space on the left third for headline text.
```
