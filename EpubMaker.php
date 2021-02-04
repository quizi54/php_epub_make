<?php

require_once 'simple_html_dom.php';

/**
 * Класс для создания epub-книг из html-файла.
 */
class EpubMaker
{
	protected $titles = [];
	protected $manifest = [];
	protected $spine = [];

	/**
	 * Description: Разбивает html-документ по пустым строкам, каждый блок сохраняет в отдельный файл
	 * @param string $path  - путь/имя html-документа
	 * @param string $dir_name  - путь директории, куда сохранять выходные файлы
	 * @return void 
	 */
	public function cut_book($path, $dir_name)
	{
		$book = file_get_contents($path);
		$arr = explode("%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%", $book);
	    foreach ($arr as $key => $value)
	    {
	        $fname = $dir_name . '/chapter' . sprintf('%02d', (1+(int)$key)) . '.xhtml';
			$header = file_get_contents('parts/xhtml_header.tpl');
			$footer = file_get_contents('parts/xhtml_footer.tpl');
	        $title = str_get_html($value)->find('h1,h2,h3', 0)->plaintext;
			$header = str_replace("###", $title, $header);
		    file_put_contents($fname, $header . $value . $footer );
		    $header = '';
		    $footer = '';
	    }
	}

	/**
	 * Description: сбор заголовков из частей книги (содержимое тегов <title> из всех файлов
	 * директории 'book/OEBPS/Text')
	 * @return void
	 */
	protected function get_titles()
	{
		$dir = 'book/OEBPS/Text';
		$files = scandir($dir);
		$arr = [];
		$i = 0;

		foreach ($files as $key => $value) 
		{
			if (!in_array($value,array(".","..")))
	        { 
			    $html = file_get_html($dir . DIRECTORY_SEPARATOR . $value);
			    $title = $html->find('title',0)->plaintext;
			    $title = ($title != '') ? $title : '* * *';
			    $arr[$i]['file'] = $value;
			    $arr[$i]['title'] = trim($title);
			    $i++;
			}
		}
		$this->titles = $arr;
	}

	/**
	 * Description: сборка toc.ncx
	 * @param array $params 
	 * @return void
	 */
	protected function make_tocncx($params)
	{
		$blocks = [];
		$doctitle = $params['title'];
		$uuid = $params['uuid'];
		$header = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\" ?>
		<ncx xmlns=\"http://www.daisy.org/z3986/2005/ncx/\" version=\"2005-1\" xml:lang=\"en\">
		<head>
			<meta content=\"$uuid\" name=\"dtb:uid\"/>
			<meta content=\"2\" name=\"dtb:depth\"/>
			<meta content=\"0\" name=\"dtb:totalPageCount\"/>
			<meta content=\"0\" name=\"dtb:maxPageNumber\"/>
		</head>
		<docTitle>
			<text>$doctitle</text>
		</docTitle>
		<navMap>\n\n";

		$footer = "	</navMap>\n</ncx>\n";

		array_push($blocks, $this->toc_elem([
			'title' => 'Обложка',
			'file'  => 'bookcover.xhtml'
		], 1));
		array_push($blocks, $this->toc_elem([
			'title' => $doctitle,
			'file'  => 'title.xhtml'
		], 2));
		array_push($blocks, $this->toc_elem([
			'title' => 'Оглавление',
			'file'  => 'toc.xhtml'
		], 3));

		$num = 4;
		foreach ($this->titles as $key => $value)
		{
			if (!in_array($value['file'], array('bookcover.xhtml','title.xhtml','toc.xhtml'))) {
				$block = $this->toc_elem($value, $num);
				array_push($blocks, $block);
				++$num;
			}
		}
		file_put_contents('book/OEBPS/toc.ncx', $header . implode('', $blocks) . $footer);
	}

	/**
	 * Description: сборка отдельного элемента (пункта) оглавления toc.ncx
	 * @param array $params 
	 * @param integer $num 
	 * @return string
	 */
	protected function toc_elem($params, $num)
	{
		$id = explode('.', $params['file'])[0];
		$title = $params['title'];
	 	$file  = $params['file'];
	    $block = "\t\t<navPoint class=\"chapter\" id=\"$id\" playOrder=\"$num\">
			<navLabel>
				<text>$title</text>
			</navLabel>
			<content src=\"Text/$file\"/>
		</navPoint>\n\n";
		return $block;
	}

	/**
	 * Description: сборка toc.xhtml
	 * @param array $params 
	 * @return void
	 */
	protected function make_tochtml($params)
	{
		$blocks   = [];
		$doctitle = $params['title'];
		$author   = $params['author'];

		$header = file_get_contents('parts/xhtml_header.tpl');
		$footer = file_get_contents('parts/xhtml_footer.tpl');
		$header = str_replace("###", "Оглавление", $header);
		$header .= "\t\t<h1>Оглавление</h1>
			<br/><br/>
			<div class=\"centeraligntext\">
				<h2>$doctitle</h2>
				<h3>$author</h3>
		  	</div>\n";

			array_push($blocks, "\t\t<a href=\"../Text/bookcover.xhtml\">Обложка</a>\n\t\t<br/>\n");
			array_push($blocks, "\t\t<a href=\"../Text/title.xhtml\">$doctitle</a>\n\t\t<br/>\n");
			array_push($blocks, "\t\t<a href=\"../Text/toc.xhtml\">Оглавление</a>\n\t\t<br/>\n");

			foreach ($this->titles as $key => $value) 
			{
				if (!in_array($value['file'], array('title.xhtml','bookcover.xhtml','toc.xhtml')))
				{
					$title = $value['title'];
					$file  = $value['file'];
					$block = "\t\t".'<a href="../Text/'.$file.'">'.$title."</a>\n\t\t<br/>\n";
					array_push($blocks, $block);
				}
			}

			file_put_contents('book/OEBPS/Text/toc.xhtml', $header.implode('', $blocks).$footer);
			array_push($this->titles, ['file' => 'toc.xhtml', 'title' => 'Оглавление']);
	}

	/**
	 * Description: функция создающая структуру epub документа (директории)
	 * @return void
	 */
	public function make_dirs()
	{
		mkdir('book', 0777);
		mkdir('book/META-INF', 0777);
		mkdir('book/OEBPS/Text', 0777, true);
		mkdir('book/OEBPS/Images', 0777, true);
		mkdir('book/OEBPS/Styles', 0777, true);
	}

	/**
	 * Description: сборка блока manifest для файла content.opf 
	 * @return void
	 */
	protected function make_manifest()
	{
		$blocks = [];
		// texts
		foreach ($this->titles as $key => $value)
		{
			$id    = explode('.', $value['file'])[0];
			$file  = $value['file'];
			$block = "\t\t<item href=\"Text/$file\" id=\"$id\" media-type=\"application/xhtml+xml\"/>";
			array_push($blocks, $block);
		}

		// images
		$images = scandir('book/OEBPS/Images');
		foreach ($images as $key => $value) 
		{
			if (!in_array($value,array(".","..")))
	        {
	        	$id    = explode('.', $value)[0];
	        	$ext   = explode('.', $value)[1];
	        	$ext   = ($ext == 'jpg') ? 'jpeg' : $ext;
			    $block = "\t\t<item href=\"Images/$value\" id=\"$id\" media-type=\"image/$ext\"/>";
			    array_push($blocks, $block);
			}
		}
		// css
		array_push($blocks,"\t\t<item href=\"Styles/stylesheet.css\" id=\"cascadingstylesheet\" media-type=\"text/css\"/>");
		// toc.ncx
        array_push($blocks,"\t\t<item href=\"toc.ncx\" id=\"tableofcontents\" media-type=\"application/x-dtbncx+xml\"/>");
	    $this->manifest = $blocks;
	}

	/**
	 * Description: сборка блока spine для файла content.opf
	 * @return void
	 */
	protected function make_spine()
	{
	    $blocks = [];
		
		array_push($blocks, "\t\t<itemref idref=\"bookcover\"/>");
		array_push($blocks, "\t\t<itemref idref=\"title\"/>");
		array_push($blocks, "\t\t<itemref idref=\"toc\"/>");
		foreach ($this->titles as $key => $value)
		{
			$id = explode('.', $value['file'])[0];
			if (!in_array($id, array('bookcover','title','toc'))) {
				$block = "\t\t<itemref idref=\"$id\"/>";
				array_push($blocks, $block);
			}
		}
	    $this->spine = $blocks;
	}

	/**
	 * Description: сборка content.opf
	 * @param array $params 
	 * @return void
	 */
	protected function make_contentopf($params)
	{
		$title     = $params['title'];
		$uuid      = $params['uuid'];
		$author    = $params['author'];
		$publisher = $params['publisher'];
		$date      = $params['date'];

	    $header = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\" ?>
	<package xmlns=\"http://www.idpf.org/2007/opf\" unique-identifier=\"uuid_id\" version=\"2.0\">
    <metadata xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:dcterms=\"http://purl.org/dc/terms/\" xmlns:opf=\"http://www.idpf.org/2007/opf\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
        <dc:title>$title</dc:title>
        <dc:language>ru</dc:language>
        <dc:identifier id=\"uuid_id\" opf:scheme=\"uuid\">$uuid</dc:identifier>
        <dc:creator>$author</dc:creator>
        <dc:publisher>$publisher</dc:publisher>
        <dc:date>$date</dc:date>
        <meta name=\"cover\" content=\"cover\"/>
    </metadata>
    <manifest>\n";
	    $midle = "\n    </manifest>
	<spine toc=\"tableofcontents\">\n";
	    $footer = "\n    </spine>
    <guide>
        <reference href=\"Text/bookcover.xhtml\" title=\"Обложка\" type=\"cover\"/>
        <reference href=\"Text/toc.xhtml\" title=\"Оглавление\" type=\"toc\"/>
    </guide>
</package>";
		$result = $header . implode("\n", $this->manifest) . $midle . implode("\n", $this->spine) . $footer;
		file_put_contents("book/OEBPS/content.opf", $result);
	}


	/**
	 * Description: сборка файла mimetype
	 * @return void
	 */
	protected function make_mimetype()
	{
	    file_put_contents('book/mimetype', 'application/epub+zip');
	}


	/**
	 * Description: сборка файла container.xml
	 * @return void
	 */
	protected function make_containerxml()
	{
		copy('parts/container.xml', 'book/META-INF/container.xml');
	}

	/**
	 * Description: сборка страницы обложки книги
	 * @return void
	 */
	protected function make_bookcover()
	{
	    $header = file_get_contents('parts/xhtml_header.tpl');
	    $header = str_replace('###', 'Обложка', $header);
	    $header = str_replace(' class="mainbody"', '', $header);
	    $header = str_replace(' class="paragraphtext"', '', $header);
	    $footer = file_get_contents('parts/xhtml_footer.tpl');
	    $body = "<svg xmlns=\"http://www.w3.org/2000/svg\" height=\"100%\" version=\"1.1\" viewBox=\"0 0 548 800\" width=\"100%\" xmlns:xlink=\"http://www.w3.org/1999/xlink\">
                <image height=\"800\" width=\"548\" xlink:href=\"../Images/cover.jpg\"/>
            </svg>";
        $content = $header . $body . $footer;
        file_put_contents('book/OEBPS/Text/bookcover.xhtml', $content);
	}

	/**
	 * Description: сборка титульной страницы
	 * @param array $params 
	 * @return void
	 */
	protected function make_titlepage($params)
	{
	    $header = file_get_contents('parts/xhtml_header.tpl');
	    $header = str_replace('###', $params['title'], $header);
	    $header = str_replace('paragraphtext', 'centeraligntext', $header);
	    $footer = file_get_contents('parts/xhtml_footer.tpl');
	    $body   = '<h1>' . $params['title'] . "</h1>\n<h2>" . $params['author'] . "</h2>\n";
	    $content = $header . $body . $footer;
        file_put_contents('book/OEBPS/Text/title.xhtml', $content);
	}

	/**
	 * Description: сборка (копирование) таблицы стилей
	 * @return void
	 */
	protected function make_stylesheet()
	{
	    copy('parts/stylesheet.css', 'book/OEBPS/Styles/stylesheet.css');
	}

	/**
	 * Description: упаковка файлов в zip-архив, переименование в epub
	 * @return void
	 */
	public function make_epub()
	{
		$z = new ZipArchive();
		if(true === ($z->open('book/book.zip', ZipArchive::CREATE)))
		{
			$z->addFile('book/mimetype', 'mimetype');
			$z->addEmptyDir('META-INF');
			$z->addFile('book/META-INF/container.xml', 'META-INF/container.xml');
			$z->addEmptyDir('OEBPS');
			$z->addFile('book/OEBPS/content.opf', 'OEBPS/content.opf');
			$z->addFile('book/OEBPS/toc.ncx', 'OEBPS/toc.ncx');
			$z->addEmptyDir('OEBPS/Images');
			$imgs = scandir('book/OEBPS/Images');
			foreach ($imgs as $key => $value)
			{
				if (!in_array($value, array('.','..'))) 
				{
					$z->addFile('book/OEBPS/Images/'.$value, 'OEBPS/Images/'.$value);
				}
			}
			$z->addEmptyDir('OEBPS/Styles');
			$z->addFile('book/OEBPS/Styles/stylesheet.css', 'OEBPS/Styles/stylesheet.css');
			$z->addEmptyDir('OEBPS/Text');
			$texts = scandir('book/OEBPS/Text');
			foreach ($texts as $key => $value)
			{
				if (!in_array($value, array('.','..'))) 
				{
					$z->addFile('book/OEBPS/Text/'.$value, 'OEBPS/Text/'.$value);
				}
			}
			$z->close();
    		rename("book/book.zip", "book/book.epub");
		} 
		else 
		{
		    echo 'zip failed<br/>';
		}
	}

	/**
	 * Description: выполнение всех шагов сборки кроме make_dirs() и cut_book()
	 * @param array $params 
	 * @return void
	 */
	public function make_all($params)
	{
	    $this->make_bookcover();
		$this->make_titlepage($params);
		$this->get_titles();
		$this->make_tochtml($params);
		$this->make_stylesheet();
		$this->make_tocncx($params);
		$this->make_manifest();
		$this->make_spine();
		$this->make_contentopf($params);
		$this->make_mimetype();
		$this->make_containerxml();
		$this->make_epub();
	}

}
