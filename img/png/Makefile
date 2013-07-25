# $Id: Makefile,v 1.1 2013/01/07 10:00:01 alfred Exp $

all: file.png dir.png

%.png: ../%.svg
	inkscape \
	  --export-png=$@ \
	  --export-dpi=90 \
	  $^
	advpng -z4 $@
