favicon.ico: ../logo.svg
	inkscape \
	  --export-png=tmp.png \
	  --export-dpi=360 \
	  --export-id=shakers \
	  $^
	pngtopnm -alpha \
	< tmp.png \
	| pamscale -width 16 \
	| pnmpad -top 1 -bottom 1 -black \
	> a.pgm
	pngtopnm \
	< tmp.png \
	| pamscale -width 16 -filter=sinc \
	| pnmpad -top 1 -bottom 1 -white \
	> n.ppm
	ppmtowinicon -andpgms n.ppm a.pgm > $@
	rm tmp.png n.ppm a.pgm
