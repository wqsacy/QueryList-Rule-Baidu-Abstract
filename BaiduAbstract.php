<?php

	namespace QL\Ext;

	use QL\Contracts\PluginContract;
	use QL\QueryList;

	class BaiduAbstract implements PluginContract
	{

		const API = 'https://www.baidu.com/s';
		const RULES = [
			'title'    => [ 'h3' , 'text' ] ,
			'link'     => [ 'h3>a' , 'href' ] ,
			'abstract' => [ '.c-abstract' , 'text' , '-span' ] ,
		];
		const RANGE = '.result';
		
		const RULES2 = [
			'word'    => [ 'a' , 'text' ] ,
			'link'    => [ 'a' , 'href' ] ,
		];
		const RANGE2 = '#rs .new-inc-rs-table th';
		
		protected $ql;
		protected $ql2;
		protected $keyword;
		protected $pageNumber = 10;
		protected $httpOpt = [
			'headers' => [
				'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36' ,
				'Accept-Encoding' => 'gzip, deflate, br' ,
			]
		];

		public function __construct ( QueryList $ql , $pageNumber ) {
			$this->ql = $ql->rules( self::RULES )
			               ->range( self::RANGE );
			
			$this->ql2 = $ql->rules( self::RULES2 )
			               ->range( self::RANGE2 );
			$this->pageNumber = $pageNumber;
		}

		public static function install ( QueryList $queryList , ...$opt ) {
			$name = $opt[0] ?? 'baiduAbstract';
			$queryList->bind( $name , function ( $pageNumber = 10 )
			{
				return new BaiduAbstract( $this , $pageNumber );
			} );
		}

		public function setHttpOpt ( array $httpOpt = [] ) {
			$this->httpOpt = $httpOpt;
			return $this;
		}

		public function search ( $keyword ) {
			$this->keyword = $keyword;
			return $this;
		}

		public function page ( $page = 1 , $realURL = false , $relSearch=false) {
			if(!$relSearch){
				
				return $this->query( $page )
				            ->query()
				            ->getData( function ( $item ) use ( $realURL )
				            {
					            $realURL && $item['link'] = $this->getRealURL( $item['link'] );
					            return $item;
				            } );
			}else{
				
				return [
					'search' =>  $this->query( $page )
					                  ->query()
					                  ->getData( function ( $item ) use ( $realURL )
					                  {
						                  $realURL && $item['link'] = $this->getRealURL( $item['link'] );
						                  return $item;
					                  } ),
					
					'rel_search' => $this->query2( $page )->query()->getData( function ( $item )
					{
						$item['link'] = 'https://www.baidu.com'.$item['link'];
						return $item;
					} ),
				];
			}
		}

		protected function query ( $page = 1 ) {
			$this->ql->get( self::API , [
				'wd' => $this->keyword ,
				'rn' => $this->pageNumber ,
				'pn' => $this->pageNumber * ($page - 1)
			] , $this->httpOpt );
			return $this->ql;
		}
		
		protected function query2 ( $page = 1 ) {
			$this->ql2->get( self::API , [
				'wd' => $this->keyword ,
				'rn' => $this->pageNumber ,
				'pn' => $this->pageNumber * ($page - 1)
			] , $this->httpOpt );
			return $this->ql2;
		}

		/**
		 * 得到百度跳转的真正地址
		 * @param $url
		 * @return mixed
		 */
		protected function getRealURL ( $url ) {
			if ( empty( $url ) ) {
				return $url;
			}
			$header = get_headers( $url , 1 );
			if ( strpos( $header[0] , '301' ) || strpos( $header[0] , '302' ) ) {
				if ( is_array( $header['Location'] ) ) {
					//return $header['Location'][count($header['Location'])-1];
					return $header['Location'][0];
				} else {
					return $header['Location'];
				}
			} else {
				return $url;
			}
		}

		public function getCountPage () {
			$count = $this->getCount();
			$countPage = ceil( $count / $this->pageNumber );
			return $countPage;
		}

		public function getCount () {
			$count = 0;
			$text = $this->query( 1 )
			             ->find( '.nums' )
			             ->text();
			if ( preg_match( '/[\d,]+/' , $text , $arr ) ) {
				$count = str_replace( ',' , '' , $arr[0] );
			}
			return (int) $count;
		}

	}