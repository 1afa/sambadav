.PHONY: clean
.INTERMEDIATE: sp1.png sp2.png sp3.png sp4.png sp5.png sp6.png sp7.png sp8.png

spinner.gif: sp1.png sp2.png sp3.png sp4.png sp5.png sp6.png sp7.png sp8.png
	convert $^ $@

sp%.png: ../spinner.svg
	inkscape \
	  --export-png=tmp.png \
	  --export-dpi=360 \
	  --export-id=frame$* \
	  "$^"
	pngtopnm -mix -background \#ffffff \
	< tmp.png \
	| pamscale 0.25 \
	| pnmtopng \
	> "$@"
	rm tmp.png

clean:
	rm spinner.gif sp*.png
