IMGS = file.png dir.png logo.png logo-150px.png shakers.png

all: $(IMGS)

%.png: ../%.svg
	inkscape \
	  --export-png=$@ \
	  --export-dpi=90 \
	  $^
	advpng -z4 $@

logo.png: ../logo.svg
	inkscape \
	  --export-png=tmp.png \
	  --export-dpi=360 \
	  $^
	pngtopnm -mix -background \#ffffff \
	< tmp.png \
	| pamscale 0.25 \
	| pnmtopng \
	> $@
	advpng -z4 $@
	rm tmp.png

logo-150px.png: ../logo.svg
	inkscape \
	  --export-png=tmp.png \
	  --export-dpi=360 \
	  $^
	pngtopnm -mix -background \#ffffff \
	< tmp.png \
	| pamscale -width 150 -filter=sinc \
	| pnmtopng \
	> $@
	advpng -z4 $@
	rm tmp.png

shakers.png: ../logo.svg
	inkscape \
	  --export-png=tmp.png \
	  --export-dpi=360 \
	  --export-id=shakers \
	  $^
	pngtopnm -mix -background \#ffffff \
	< tmp.png \
	| pamscale 0.3 \
	| pnmpad -left 4 -right 4 -top 4 -bottom 4 -white \
	| pnmtopng \
	> $@
	advpng -z4 $@
	rm tmp.png

clean:
	rm $(IMGS)
