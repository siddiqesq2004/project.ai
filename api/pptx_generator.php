<?php
function generatePPTX($slides, $title, $outputPath) {
    $zip = new ZipArchive();
    if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) return false;

    $emu_w = 9144000; $emu_h = 6858000;
    $slideCount = count($slides);

    // [Content_Types].xml
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/><Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/><Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/><Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>';
    for ($i = 1; $i <= $slideCount; $i++) {
        $ct .= '<Override PartName="/ppt/slides/slide'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
    }
    $ct .= '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>';
    $zip->addFromString('[Content_Types].xml', $ct);

    // _rels/.rels
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');

    // docProps/core.xml
    $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/"><dc:title>'.htmlspecialchars($title).'</dc:title><dc:creator>Project AI Studio</dc:creator></cp:coreProperties>');

    // ppt/presentation.xml
    $pres = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst><p:sldIdLst>';
    for ($i = 1; $i <= $slideCount; $i++) {
        $pres .= '<p:sldId id="'.(255+$i).'" r:id="rId'.($i+1).'"/>';
    }
    $pres .= '</p:sldIdLst><p:sldSz cx="'.$emu_w.'" cy="'.$emu_h.'" type="screen4x3"/></p:presentation>';
    $zip->addFromString('ppt/presentation.xml', $pres);

    // ppt/_rels/presentation.xml.rels
    $presRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>';
    for ($i = 1; $i <= $slideCount; $i++) {
        $presRels .= '<Relationship Id="rId'.($i+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide'.$i.'.xml"/>';
    }
    $presRels .= '<Relationship Id="rId'.($slideCount+2).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/></Relationships>';
    $zip->addFromString('ppt/_rels/presentation.xml.rels', $presRels);

    // Theme
    $zip->addFromString('ppt/theme/theme1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="ProjectAI"><a:themeElements><a:clrScheme name="Dark"><a:dk1><a:srgbClr val="0F172A"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1><a:dk2><a:srgbClr val="1E293B"/></a:dk2><a:lt2><a:srgbClr val="E2E8F0"/></a:lt2><a:accent1><a:srgbClr val="38BDF8"/></a:accent1><a:accent2><a:srgbClr val="FF2A5F"/></a:accent2><a:accent3><a:srgbClr val="A78BFA"/></a:accent3><a:accent4><a:srgbClr val="34D399"/></a:accent4><a:accent5><a:srgbClr val="FB923C"/></a:accent5><a:accent6><a:srgbClr val="F472B6"/></a:accent6><a:hlink><a:srgbClr val="38BDF8"/></a:hlink><a:folHlink><a:srgbClr val="A78BFA"/></a:folHlink></a:clrScheme><a:fontScheme name="Office"><a:majorFont><a:latin typeface="Calibri"/></a:majorFont><a:minorFont><a:latin typeface="Calibri"/></a:minorFont></a:fontScheme><a:fmtScheme name="Office"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln w="9525"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="9525"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln><a:ln w="9525"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme></a:themeElements></a:theme>');

    // Slide Layout
    $zip->addFromString('ppt/slideLayouts/slideLayout1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/></p:spTree></p:cSld></p:sldLayout>');
    $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/></Relationships>');

    // Slide Master
    $zip->addFromString('ppt/slideMasters/slideMaster1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:bg><p:bgPr><a:solidFill><a:srgbClr val="0F172A"/></a:solidFill><a:effectLst/></p:bgPr></p:bg><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/></p:spTree></p:cSld><p:clrMap bg1="dk1" tx1="lt1" bg2="dk2" tx2="lt2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/><p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst></p:sldMaster>');
    $zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/></Relationships>');

    // Generate each slide
    for ($i = 0; $i < $slideCount; $i++) {
        $slide = $slides[$i];
        $type = $slide['type'] ?? 'content';
        $slideTitle = htmlspecialchars($slide['title'] ?? 'Slide '.($i+1));
        $subtitle = htmlspecialchars($slide['subtitle'] ?? '');
        $points = $slide['points'] ?? [];
        $slideNum = $i + 1;

        // Background gradient fill
        $bgXml = '<p:bg><p:bgPr><a:gradFill><a:gsLst><a:gs pos="0"><a:srgbClr val="0F172A"/></a:gs><a:gs pos="100000"><a:srgbClr val="1E293B"/></a:gs></a:gsLst><a:lin ang="8100000"/></a:gradFill><a:effectLst/></p:bgPr></p:bg>';

        // Bottom accent bar shape
        $accentBar = '<p:sp><p:nvSpPr><p:cNvPr id="100" name="AccentBar"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="0" y="6758000"/><a:ext cx="'.$emu_w.'" cy="100000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom><a:gradFill><a:gsLst><a:gs pos="0"><a:srgbClr val="38BDF8"/></a:gs><a:gs pos="100000"><a:srgbClr val="FF2A5F"/></a:gs></a:gsLst><a:lin ang="0"/></a:gradFill></p:spPr></p:sp>';

        if ($type === 'title') {
            // Title slide: centered title + subtitle
            $slideXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld>'.$bgXml.'<p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>';
            // Title
            $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="457200" y="1800000"/><a:ext cx="8229600" cy="1500000"/></a:xfrm></p:spPr><p:txBody><a:bodyPr anchor="ctr"/><a:p><a:pPr algn="ctr"/><a:r><a:rPr lang="en-US" sz="3600" b="1" dirty="0"><a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:latin typeface="Calibri"/></a:rPr><a:t>'.$slideTitle.'</a:t></a:r></a:p></p:txBody></p:sp>';
            // Subtitle
            $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="3" name="Subtitle"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="457200" y="3400000"/><a:ext cx="8229600" cy="600000"/></a:xfrm></p:spPr><p:txBody><a:bodyPr anchor="ctr"/><a:p><a:pPr algn="ctr"/><a:r><a:rPr lang="en-US" sz="2000" dirty="0"><a:solidFill><a:srgbClr val="38BDF8"/></a:solidFill><a:latin typeface="Calibri"/></a:rPr><a:t>'.$subtitle.'</a:t></a:r></a:p></p:txBody></p:sp>';
            // Presented by
            $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="4" name="Info"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="457200" y="4400000"/><a:ext cx="8229600" cy="600000"/></a:xfrm></p:spPr><p:txBody><a:bodyPr anchor="t"/><a:p><a:pPr algn="ctr"/><a:r><a:rPr lang="en-US" sz="1400" dirty="0"><a:solidFill><a:srgbClr val="94A3B8"/></a:solidFill><a:latin typeface="Calibri"/></a:rPr><a:t>Academic Project Presentation</a:t></a:r></a:p></p:txBody></p:sp>';
            $slideXml .= $accentBar.'</p:spTree></p:cSld></p:sld>';
        } else {
            // Content slide: title + subtitle + bullet points
            $slideXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld>'.$bgXml.'<p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>';
            // Slide number badge
            $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="5" name="Badge"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="300000" y="200000"/><a:ext cx="1200000" cy="350000"/></a:xfrm><a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 16667"/></a:avLst></a:prstGeom><a:solidFill><a:srgbClr val="38BDF8"><a:alpha val="20000"/></a:srgbClr></a:solidFill><a:ln w="12700"><a:solidFill><a:srgbClr val="38BDF8"/></a:solidFill></a:ln></p:spPr><p:txBody><a:bodyPr anchor="ctr"/><a:p><a:pPr algn="ctr"/><a:r><a:rPr lang="en-US" sz="1000" b="1" dirty="0"><a:solidFill><a:srgbClr val="38BDF8"/></a:solidFill></a:rPr><a:t>SLIDE '.$slideNum.'</a:t></a:r></a:p></p:txBody></p:sp>';
            // Title
            $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="2" name="Title"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="457200" y="700000"/><a:ext cx="8229600" cy="700000"/></a:xfrm></p:spPr><p:txBody><a:bodyPr anchor="b"/><a:p><a:r><a:rPr lang="en-US" sz="2800" b="1" dirty="0"><a:solidFill><a:srgbClr val="38BDF8"/></a:solidFill><a:latin typeface="Calibri"/></a:rPr><a:t>'.$slideTitle.'</a:t></a:r></a:p></p:txBody></p:sp>';
            // Subtitle
            $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="3" name="Subtitle"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="457200" y="1450000"/><a:ext cx="8229600" cy="400000"/></a:xfrm></p:spPr><p:txBody><a:bodyPr/><a:p><a:r><a:rPr lang="en-US" sz="1600" dirty="0"><a:solidFill><a:srgbClr val="94A3B8"/></a:solidFill><a:latin typeface="Calibri"/></a:rPr><a:t>'.$subtitle.'</a:t></a:r></a:p></p:txBody></p:sp>';
            // Divider line
            $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="6" name="Line"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="457200" y="1900000"/><a:ext cx="8229600" cy="0"/></a:xfrm><a:prstGeom prst="line"><a:avLst/></a:prstGeom><a:ln w="19050"><a:solidFill><a:srgbClr val="38BDF8"><a:alpha val="30000"/></a:srgbClr></a:solidFill></a:ln></p:spPr></p:sp>';
            // Bullet points
            $bulletXml = '';
            foreach ($points as $pt) {
                $bulletXml .= '<a:p><a:pPr marL="457200" indent="-228600"><a:buFont typeface="Arial"/><a:buChar char="&#x2022;"/></a:pPr><a:r><a:rPr lang="en-US" sz="1600" dirty="0"><a:solidFill><a:srgbClr val="E2E8F0"/></a:solidFill><a:latin typeface="Calibri"/></a:rPr><a:t>'.htmlspecialchars($pt).'</a:t></a:r></a:p>';
            }
            $slideXml .= '<p:sp><p:nvSpPr><p:cNvPr id="4" name="Content"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="457200" y="2100000"/><a:ext cx="8229600" cy="4200000"/></a:xfrm></p:spPr><p:txBody><a:bodyPr anchor="t"/>'.$bulletXml.'</p:txBody></p:sp>';
            $slideXml .= $accentBar.'</p:spTree></p:cSld></p:sld>';
        }

        $zip->addFromString('ppt/slides/slide'.$slideNum.'.xml', $slideXml);
        $zip->addFromString('ppt/slides/_rels/slide'.$slideNum.'.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/></Relationships>');
    }

    $zip->close();
    return true;
}
?>
