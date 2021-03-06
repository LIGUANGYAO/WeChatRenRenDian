<?php
if (!(defined('IN_IA'))) 
{
	exit('Access Denied');
}
class Detail_EweiShopV2Page extends MobilePage 
{
	public function main() 
	{
		global $_W;
		global $_GPC;
		$openid = $_W['openid'];
		$uniacid = $_W['uniacid'];
		$id = intval($_GPC['id']);
		$rank = intval($_GPC['rank']);
		$join_id = intval($_GPC['join_id']);
		if (!(empty($join_id))) 
		{
			$_SESSION[$id . '_rank'] = $rank;
			$_SESSION[$id . '_join_id'] = $join_id;
		}
		$err = false;
		$merch_plugin = p('merch');
		$merch_data = m('common')->getPluginset('merch');
		$commission_data = m('common')->getPluginset('commission');
		if ($merch_plugin && $merch_data['is_openmerch']) 
		{
			$is_openmerch = 1;
		}
		else 
		{
			$is_openmerch = 0;
		}
		$isgift = 0;
		$gifts = array();
		$giftgoods = array();
		$gifts = pdo_fetchall('select id,goodsid,giftgoodsid,thumb,title from ' . tablename('ewei_shop_gift') . ' where uniacid = ' . $uniacid . ' and activity = 2 and status = 1 and starttime <= ' . time() . ' and endtime >= ' . time() . '  ');
		foreach ($gifts as $key => $value ) 
		{
			if (strstr($value['goodsid'], trim($id))) 
			{
				$giftgoods = explode(',', $value['giftgoodsid']);
				foreach ($giftgoods as $k => $val ) 
				{
					$isgift = 1;
					$gifts[$key]['gift'][$k] = pdo_fetch('select id,title,thumb,marketprice from ' . tablename('ewei_shop_goods') . ' where uniacid = ' . $uniacid . ' and deleted = 0 and total > 0 and status = 2 and id = ' . $val . ' ');
					$gifttitle = ((!(empty($gifts[$key]['gift'][$k]['title'])) ? $gifts[$key]['gift'][$k]['title'] : '赠品'));
				}
			}
			else 
			{
				unset($gifts[$key]);
			}
		}
		$goods = pdo_fetch('select * from ' . tablename('ewei_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $id, ':uniacid' => $_W['uniacid']));
		if ((0 < $goods['ispresell']) && (((0 < $goods['presellend']) && (time() < $goods['preselltimeend'])) || ($goods['preselltimeend'] == 0))) 
		{
			$goods['minprice'] = $goods['presellprice'];
			if ($goods['hasoption'] == 0) 
			{
				$goods['maxprice'] = $goods['presellprice'];
			}
		}
		$merchid = $goods['merchid'];
		$labelname = json_decode($goods['labelname'], true);
		$style = pdo_fetch('SELECT id,uniacid,style FROM ' . tablename('ewei_shop_goods_labelstyle') . ' WHERE uniacid=' . $uniacid);
		if ($is_openmerch == 0) 
		{
			if (0 < $merchid) 
			{
				$err = true;
				include $this->template('goods/detail');
				exit();
			}
		}
		else if ((0 < $merchid) && ($goods['checked'] == 1)) 
		{
			$err = true;
			include $this->template('goods/detail');
			exit();
		}
		$member = m('member')->getMember($openid);
		$showgoods = m('goods')->visit($goods, $member);
		if (empty($goods) || empty($showgoods)) 
		{
			$err = true;
			include $this->template();
			exit();
		}
		$seckillinfo = false;
		$seckill = p('seckill');
		if ($seckill) 
		{
			$time = time();
			$seckillinfo = $seckill->getSeckill($goods['id'], 0, false);
			if (!(empty($seckillinfo))) 
			{
				if (($seckillinfo['starttime'] <= $time) && ($time < $seckillinfo['endtime'])) 
				{
					$seckillinfo['status'] = 0;
				}
				else if ($time < $seckillinfo['starttime']) 
				{
					$seckillinfo['status'] = 1;
				}
				else 
				{
					$seckillinfo['status'] = -1;
				}
			}
		}
		$task_goods_data = m('goods')->getTaskGoods($openid, $id, $rank, $join_id);
		if (empty($task_goods_data['is_task_goods'])) 
		{
			$is_task_goods = 0;
			if (p('bargain')) 
			{
				$bargain = pdo_fetch('SELECT * FROM ' . tablename('ewei_shop_bargain_goods') . ' WHERE id = :id AND unix_timestamp(start_time)<' . time() . ' AND unix_timestamp(end_time)>' . time() . ' AND status = 0', array(':id' => $goods['bargain']));
				if ($bargain != false) 
				{
					echo '<script>window.location.href = \'' . mobileUrl('bargain/detail', array('id' => $goods['bargain'])) . '\'</script>';
					return;
					$is_task_goods = $task_goods_data['is_task_goods'];
					$is_task_goods_option = $task_goods_data['is_task_goods_option'];
					$task_goods = $task_goods_data['task_goods'];
				}
			}
		}
		else 
		{
			$is_task_goods = $task_goods_data['is_task_goods'];
			$is_task_goods_option = $task_goods_data['is_task_goods_option'];
			$task_goods = $task_goods_data['task_goods'];
		}
		$goods['sales'] = $goods['sales'] + $goods['salesreal'];
		$goods['content'] = m('ui')->lazy($goods['content']);
		$buyshow = 0;
		if ($goods['buyshow'] == 1) 
		{
			$sql = 'select o.id from ' . tablename('ewei_shop_order') . ' o left join ' . tablename('ewei_shop_order_goods') . ' g on o.id = g.orderid';
			$sql .= ' where o.openid=:openid and g.goodsid=:id and o.status>0 and o.uniacid=:uniacid limit 1';
			$buy_goods = pdo_fetch($sql, array(':openid' => $openid, ':id' => $id, ':uniacid' => $_W['uniacid']));
			if (!(empty($buy_goods))) 
			{
				$buyshow = 1;
				$goods['buycontent'] = m('ui')->lazy($goods['buycontent']);
			}
		}
		$goods['unit'] = ((empty($goods['unit']) ? '件' : $goods['unit']));
		$citys = m('dispatch')->getNoDispatchAreas($goods);
		if (!(empty($citys)) && is_array($citys)) 
		{
			$has_city = 1;
		}
		else 
		{
			$has_city = 0;
		}
		$package_goods = pdo_fetch('select pg.id,pg.pid,pg.goodsid,p.displayorder from ' . tablename('ewei_shop_package_goods') . ' as pg' . "\n" . '                        left join ' . tablename('ewei_shop_package') . ' as p on pg.pid = p.id' . "\n" . '                        where pg.uniacid = ' . $uniacid . ' and pg.goodsid = ' . $id . ' ORDER BY p.displayorder desc,pg.id desc limit 1 ');
		if ($package_goods['pid']) 
		{
			$packages = pdo_fetchall('SELECT id,title,thumb,packageprice FROM ' . tablename('ewei_shop_package_goods') . "\n" . '                    WHERE uniacid = ' . $uniacid . ' and pid = ' . $package_goods['pid'] . '  ORDER BY id DESC');
			$packages = set_medias($packages, array('thumb'));
		}
		$goods['dispatchprice'] = $this->getGoodsDispatchPrice($goods);
		$thumbs = iunserializer($goods['thumb_url']);
		if (empty($thumbs)) 
		{
			$thumbs = array($goods['thumb']);
		}
		if (!(empty($goods['thumb_first'])) && !(empty($goods['thumb']))) 
		{
			$thumbs = array_merge(array($goods['thumb']), $thumbs);
		}
		$specs = pdo_fetchall('select * from ' . tablename('ewei_shop_goods_spec') . ' where goodsid=:goodsid and  uniacid=:uniacid order by displayorder asc', array(':goodsid' => $id, ':uniacid' => $_W['uniacid']));
		$spec_titles = array();
		foreach ($specs as $key => $spec ) 
		{
			if (2 <= $key) 
			{
				break;
			}
			$spec_titles[] = $spec['title'];
		}
		if (0 < $goods['hasoption']) 
		{
			$spec_titles = implode('、', $spec_titles);
		}
		else 
		{
			$spec_titles = '';
		}
		$params = pdo_fetchall('SELECT * FROM ' . tablename('ewei_shop_goods_param') . ' WHERE uniacid=:uniacid and goodsid=:goodsid order by displayorder asc', array(':uniacid' => $uniacid, ':goodsid' => $goods['id']));
		$goods = set_medias($goods, 'thumb');
		$goods['canbuy'] = ($goods['status'] == 1) && empty($goods['deleted']);
		if (!(empty($goods['hasoption']))) 
		{
			$options = pdo_fetchall('select id,stock from ' . tablename('ewei_shop_goods_option') . ' where goodsid=:goodsid and uniacid=:uniacid order by displayorder asc', array(':goodsid' => $goods['id'], ':uniacid' => $_W['uniacid']), 'stock');
			$options_stock = array_keys($options);
		}
		if ($goods['total'] <= 0) 
		{
			$goods['canbuy'] = false;
		}
		if (0 < $goods['ispresell']) 
		{
			if ((0 < $goods['preselltimestart']) && (time() < $goods['preselltimestart'])) 
			{
				$goods['canbuy'] = false;
			}
			if ((0 < $goods['preselltimeend']) && ($goods['preselltimeend'] < time())) 
			{
				$goods['canbuy'] = false;
			}
			$times = ($goods['presellovertime'] * 60 * 60 * 24) + $goods['preselltimeend'];
			if ((0 < $goods['presellover']) && ($times <= time()) && (0 < $goods['preselltimeend'])) 
			{
				$goods['canbuy'] = true;
			}
		}
		if ((0 < $goods['isendtime']) && (0 < $goods['endtime']) && ($goods['endtime'] < time())) 
		{
			$goods['canbuy'] = false;
		}
		$goods['timestate'] = '';
		$goods['userbuy'] = '1';
		if (0 < $goods['usermaxbuy']) 
		{
			$order_goodscount = pdo_fetchcolumn('select ifnull(sum(og.total),0)  from ' . tablename('ewei_shop_order_goods') . ' og ' . ' left join ' . tablename('ewei_shop_order') . ' o on og.orderid=o.id ' . ' where og.goodsid=:goodsid and  o.status>=1 and o.openid=:openid  and og.uniacid=:uniacid ', array(':goodsid' => $goods['id'], ':uniacid' => $uniacid, ':openid' => $openid));
			if ($goods['usermaxbuy'] <= $order_goodscount) 
			{
				$goods['userbuy'] = 0;
				$goods['canbuy'] = false;
			}
		}
		$levelid = $member['level'];
		$groupid = $member['groupid'];
		$goods['levelbuy'] = '1';
		if ($goods['buylevels'] != '') 
		{
			$buylevels = explode(',', $goods['buylevels']);
			if (!(in_array($levelid, $buylevels))) 
			{
				$goods['levelbuy'] = 0;
				$goods['canbuy'] = false;
			}
		}
		$goods['groupbuy'] = '1';
		if ($goods['buygroups'] != '') 
		{
			$buygroups = explode(',', $goods['buygroups']);
			if (!(in_array($groupid, $buygroups))) 
			{
				$goods['groupbuy'] = 0;
				$goods['canbuy'] = false;
			}
		}
		$goods['timebuy'] = '0';
		if (empty($seckillinfo)) 
		{
			if ($goods['istime'] == 1) 
			{
				if (time() < $goods['timestart']) 
				{
					$goods['timebuy'] = '-1';
					$goods['canbuy'] = false;
				}
				else if ($goods['timeend'] < time()) 
				{
					$goods['timebuy'] = '1';
					$goods['canbuy'] = false;
				}
			}
		}
		$canAddCart = true;
		if (($goods['isverify'] == 2) || ($goods['type'] == 2) || ($goods['type'] == 3) || ($goods['type'] == 20) || !(empty($goods['cannotrefund'])) || !(empty($is_task_goods)) || !(empty($gifts))) 
		{
			$canAddCart = false;
		}
		if (($goods['type'] == 2) && empty($specs)) 
		{
			$gflag = 1;
		}
		else 
		{
			$gflag = 0;
		}
		$enoughs = com_run('sale::getEnoughs');
		$goods_nofree = com_run('sale::getEnoughsGoods');
		if (empty($is_task_goods)) 
		{
			$enoughfree = com_run('sale::getEnoughFree');
		}
		if (!(empty($goods_nofree))) 
		{
			if (in_array($id, $goods_nofree)) 
			{
				$enoughfree = false;
			}
		}
		if ($enoughfree && ($enoughfree < $goods['minprice'])) 
		{
			$goods['dispatchprice'] = 0;
		}
		$hasSales = false;
		if ((0 < $goods['ednum']) || (0 < $goods['edmoney'])) 
		{
			$hasSales = true;
		}
		if ($enoughfree || ($enoughs && (0 < count($enoughs)))) 
		{
			$hasSales = true;
		}
		$minprice = $goods['minprice'];
		$maxprice = $goods['maxprice'];
		$level = m('member')->getLevel($openid);
		if (empty($is_task_goods)) 
		{
			$memberprice = m('goods')->getMemberPrice($goods, $level);
		}
		if ($goods['isdiscount'] && (time() <= $goods['isdiscount_time'])) 
		{
			$goods['oldmaxprice'] = $maxprice;
			$prices = array();
			$isdiscount_discounts = json_decode($goods['isdiscount_discounts'], true);
			if (!(isset($isdiscount_discounts['type'])) || empty($isdiscount_discounts['type'])) 
			{
				$prices_array = m('order')->getGoodsDiscountPrice($goods, $level, 1);
				$prices[] = $prices_array['price'];
			}
			else 
			{
				$goods_discounts = m('order')->getGoodsDiscounts($goods, $isdiscount_discounts, $levelid);
				$prices = $goods_discounts['prices'];
			}
			$minprice = min($prices);
			$maxprice = max($prices);
		}
		else 
		{
			if (isset($options) && (0 < count($options)) && $goods['hasoption']) 
			{
				$optionids = array();
				foreach ($options as $val ) 
				{
					$optionids[] = $val['id'];
				}
				$sql = 'update ' . tablename('ewei_shop_goods') . ' g set' . "\n" . '        g.minprice = (select min(marketprice) from ' . tablename('ewei_shop_goods_option') . ' where goodsid = ' . $id . '),' . "\n" . '        g.maxprice = (select max(marketprice) from ' . tablename('ewei_shop_goods_option') . ' where goodsid = ' . $id . ')' . "\n" . '        where g.id = ' . $id . ' and g.hasoption=1';
				pdo_query($sql);
			}
			else 
			{
				$sql = 'update ' . tablename('ewei_shop_goods') . ' set minprice = marketprice,maxprice = marketprice where id = ' . $id . ' and hasoption=0;';
				pdo_query($sql);
			}
			$goods_price = pdo_fetch('select minprice,maxprice from ' . tablename('ewei_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $id, ':uniacid' => $_W['uniacid']));
			$maxprice = (double) $goods_price['maxprice'];
			$minprice = (double) $goods_price['minprice'];
		}
		if (!(empty($is_task_goods))) 
		{
			if (isset($options) && (0 < count($options)) && $goods['hasoption']) 
			{
				$prices = array();
				foreach ($task_goods['spec'] as $k => $v ) 
				{
					$prices[] = $v['marketprice'];
				}
				$minprice2 = min($prices);
				$maxprice2 = max($prices);
				if ($minprice2 < $minprice) 
				{
					$minprice = $minprice2;
				}
				if ($maxprice < $maxprice2) 
				{
					$maxprice = $maxprice2;
				}
			}
			else 
			{
				$minprice = $task_goods['marketprice'];
				$maxprice = $task_goods['marketprice'];
			}
		}
		if ((0 < $goods['ispresell']) && $goods['hasoption'] && (($goods['preselltimeend'] == 0) || (time() < $goods['preselltimeend']))) 
		{
			$presell = pdo_fetch('select min(presellprice) as minprice,max(presellprice) as maxprice from ' . tablename('ewei_shop_goods_option') . ' where goodsid = ' . $id);
			$minprice = $presell['minprice'];
			$maxprice = $presell['maxprice'];
		}
		$goods['minprice'] = $minprice;
		$goods['maxprice'] = $maxprice;
		$getComments = empty($_W['shopset']['trade']['closecommentshow']);
		$hasServices = $goods['cash'] || $goods['seven'] || $goods['repair'] || $goods['invoice'] || $goods['quality'];
		$isFavorite = m('goods')->isFavorite($id);
		$cartCount = m('goods')->getCartCount();
		m('goods')->addHistory($id);
		$shop = set_medias(m('common')->getSysset('shop'), 'logo');
		$shop['url'] = mobileUrl('', NULL, true);
		$mid = intval($_GPC['mid']);
		$opencommission = false;
		if (p('commission')) 
		{
			if (empty($member['agentblack'])) 
			{
				$cset = p('commission')->getSet();
				$opencommission = 0 < intval($cset['level']);
				if ($opencommission) 
				{
					if (empty($mid)) 
					{
						if (($member['isagent'] == 1) && ($member['status'] == 1)) 
						{
							$mid = $member['id'];
						}
					}
					if (!(empty($mid))) 
					{
						if (empty($cset['closemyshop'])) 
						{
							$shop = set_medias(p('commission')->getShop($mid), 'logo');
							$shop['url'] = mobileUrl('commission/myshop', array('mid' => $mid), true);
						}
					}
				}
			}
		}
		if (empty($this->merch_user)) 
		{
			$merch_flag = 0;
			if (($is_openmerch == 1) && (0 < $goods['merchid'])) 
			{
				$merch_user = pdo_fetch('select * from ' . tablename('ewei_shop_merch_user') . ' where id=:id limit 1', array(':id' => intval($goods['merchid'])));
				if (!(empty($merch_user))) 
				{
					$shop = $merch_user;
					$merch_flag = 1;
				}
			}
			if ($merch_flag == 1) 
			{
				$shopdetail = array('logo' => (!(empty($goods['detail_logo'])) ? tomedia($goods['detail_logo']) : tomedia($shop['logo'])), 'shopname' => (!(empty($goods['detail_shopname'])) ? $goods['detail_shopname'] : $shop['merchname']), 'description' => (!(empty($goods['detail_totaltitle'])) ? $goods['detail_totaltitle'] : $shop['desc']), 'btntext1' => trim($goods['detail_btntext1']), 'btnurl1' => (!(empty($goods['detail_btnurl1'])) ? $goods['detail_btnurl1'] : mobileUrl('goods')), 'btntext2' => trim($goods['detail_btntext2']), 'btnurl2' => (!(empty($goods['detail_btnurl2'])) ? $goods['detail_btnurl2'] : mobileUrl('merch', array('merchid' => $goods['merchid']))));
			}
			else 
			{
				$shopdetail = array('logo' => (!(empty($goods['detail_logo'])) ? tomedia($goods['detail_logo']) : $shop['logo']), 'shopname' => (!(empty($goods['detail_shopname'])) ? $goods['detail_shopname'] : $shop['name']), 'description' => (!(empty($goods['detail_totaltitle'])) ? $goods['detail_totaltitle'] : $shop['desc']), 'btntext1' => trim($goods['detail_btntext1']), 'btnurl1' => (!(empty($goods['detail_btnurl1'])) ? $goods['detail_btnurl1'] : mobileUrl('goods')), 'btntext2' => trim($goods['detail_btntext2']), 'btnurl2' => (!(empty($goods['detail_btnurl2'])) ? $goods['detail_btnurl2'] : $shop['url']));
			}
			$param = array(':uniacid' => $_W['uniacid']);
			if ($merch_flag == 1) 
			{
				$sqlcon = ' and merchid=:merchid';
				$param[':merchid'] = $goods['merchid'];
			}
			if (empty($shop['selectgoods'])) 
			{
				$statics = array('all' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_goods') . ' where uniacid=:uniacid ' . $sqlcon . ' and status=1 and deleted=0', $param), 'new' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_goods') . ' where uniacid=:uniacid ' . $sqlcon . ' and isnew=1 and status=1 and deleted=0', $param), 'discount' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_goods') . ' where uniacid=:uniacid ' . $sqlcon . ' and isdiscount=1 and status=1 and deleted=0', $param));
			}
			else 
			{
				$goodsids = explode(',', $shop['goodsids']);
				$statics = array('all' => count($goodsids), 'new' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_goods') . ' where uniacid=:uniacid ' . $sqlcon . ' and id in( ' . $shop['goodsids'] . ' ) and isnew=1 and status=1 and deleted=0', $param), 'discount' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_goods') . ' where uniacid=:uniacid ' . $sqlcon . ' and id in( ' . $shop['goodsids'] . ' ) and isdiscount=1 and status=1 and deleted=0', $param));
			}
		}
		else if ($goods['checked'] == 1) 
		{
			$err = true;
			include $this->template();
			exit();
		}
		else 
		{
			$shop = $this->merch_user;
			$shopdetail = array('logo' => (!(empty($goods['detail_logo'])) ? tomedia($goods['detail_logo']) : tomedia($shop['logo'])), 'shopname' => (!(empty($goods['detail_shopname'])) ? $goods['detail_shopname'] : $shop['merchname']), 'description' => (!(empty($goods['detail_totaltitle'])) ? $goods['detail_totaltitle'] : $shop['desc']), 'btntext1' => trim($goods['detail_btntext1']), 'btnurl1' => (!(empty($goods['detail_btnurl1'])) ? $goods['detail_btnurl1'] : mobileUrl('goods')), 'btntext2' => trim($goods['detail_btntext2']), 'btnurl2' => (!(empty($goods['detail_btnurl2'])) ? $goods['detail_btnurl2'] : mobileUrl('merch', array('merchid' => $goods['merchid']))));
			if (empty($shop['selectgoods'])) 
			{
				$statics = array('all' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_goods') . ' where uniacid=:uniacid and merchid=:merchid and status=1 and deleted=0', array(':uniacid' => $_W['uniacid'], ':merchid' => $goods['merchid'])), 'new' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_goods') . ' where uniacid=:uniacid and merchid=:merchid and isnew=1 and status=1 and deleted=0', array(':uniacid' => $_W['uniacid'], ':merchid' => $goods['merchid'])), 'discount' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_goods') . ' where uniacid=:uniacid and merchid=:merchid and isdiscount=1 and status=1 and deleted=0', array(':uniacid' => $_W['uniacid'], ':merchid' => $goods['merchid'])));
			}
			else 
			{
				$goodsids = explode(',', $shop['goodsids']);
				$statics = array('all' => count($goodsids), 'new' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_goods') . ' where uniacid=:uniacid and merchid=:merchid and id in( ' . $shop['goodsids'] . ' ) and isnew=1 and status=1 and deleted=0', array(':uniacid' => $_W['uniacid'], ':merchid' => $goods['merchid'])), 'discount' => pdo_fetchcolumn('select count(1) from ' . tablename('ewei_shop_goods') . ' where uniacid=:uniacid and merchid=:merchid and id in( ' . $shop['goodsids'] . ' ) and isdiscount=1 and status=1 and deleted=0', array(':uniacid' => $_W['uniacid'], ':merchid' => $goods['merchid'])));
			}
		}
		$goodsdesc = ((!(empty($goods['description'])) ? $goods['description'] : $goods['subtitle']));
		$_W['shopshare'] = array('title' => (!(empty($goods['share_title'])) ? $goods['share_title'] : $goods['title']), 'imgUrl' => (!(empty($goods['share_icon'])) ? tomedia($goods['share_icon']) : tomedia($goods['thumb'])), 'desc' => (!(empty($goodsdesc)) ? $goodsdesc : $_W['shopset']['shop']['name']), 'link' => mobileUrl('goods/detail', array('id' => $goods['id']), true));
		$com = p('commission');
		if ($com) 
		{
			$cset = $_W['shopset']['commission'];
			if (!(empty($cset))) 
			{
				if (($member['isagent'] == 1) && ($member['status'] == 1)) 
				{
					$_W['shopshare']['link'] = mobileUrl('goods/detail', array('id' => $goods['id'], 'mid' => $member['id']), true);
				}
				else if (!(empty($_GPC['mid']))) 
				{
					$_W['shopshare']['link'] = mobileUrl('goods/detail', array('id' => $goods['id'], 'mid' => $_GPC['mid']), true);
				}
			}
		}
		$stores = array();
		if ($goods['isverify'] == 2) 
		{
			$storeids = array();
			if (!(empty($goods['storeids']))) 
			{
				$storeids = array_merge(explode(',', $goods['storeids']), $storeids);
			}
			if (empty($storeids)) 
			{
				if (0 < $merchid) 
				{
					$stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where  uniacid=:uniacid and merchid=:merchid and status=1 ', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
				}
				else 
				{
					$stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where  uniacid=:uniacid and status=1', array(':uniacid' => $_W['uniacid']));
				}
			}
			else if (0 < $merchid) 
			{
				$stores = pdo_fetchall('select * from ' . tablename('ewei_shop_merch_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and merchid=:merchid and status=1', array(':uniacid' => $_W['uniacid'], ':merchid' => $merchid));
			}
			else 
			{
				$stores = pdo_fetchall('select * from ' . tablename('ewei_shop_store') . ' where id in (' . implode(',', $storeids) . ') and uniacid=:uniacid and status=1', array(':uniacid' => $_W['uniacid']));
			}
		}
		$share = m('common')->getSysset('share');
		$share['goods_detail_text'] = nl2br($share['goods_detail_text']);
		if (p('ccard') && ($goods['type'] == 20)) 
		{
			$diyformhtml = '';
			$diyform_plugin = p('diyform');
			if ($diyform_plugin) 
			{
				$fields = false;
				if ($goods['diyformtype'] == 1) 
				{
					if (!(empty($goods['diyformid']))) 
					{
						$diyformid = $goods['diyformid'];
						$formInfo = $diyform_plugin->getDiyformInfo($diyformid);
						$fields = $formInfo['fields'];
					}
				}
				else if ($goods['diyformtype'] == 2) 
				{
					$diyformid = 0;
					$fields = iunserializer($goods['diyfields']);
					if (empty($fields)) 
					{
						$fields = false;
					}
				}
				if (!(empty($fields))) 
				{
					ob_start();
					$inPicker = true;
					$openid = $_W['openid'];
					$member = m('member')->getMember($openid, true);
					$f_data = $diyform_plugin->getLastData(3, 0, $diyformid, $id, $fields, $member);
					$flag = 0;
					if (!(empty($f_data))) 
					{
						foreach ($f_data as $k => $v ) 
						{
							if (!(empty($v))) 
							{
								$flag = 1;
								break;
							}
						}
					}
					if (empty($flag)) 
					{
						$f_data = $diyform_plugin->getLastCartData($id);
					}
					$f_data['diychongzhijine'] = $goods['minprice'];
					include $this->template('ccard/formfields');
					$diyformhtml = ob_get_contents();
					ob_clean();
				}
			}
			include $this->template('ccard/ccard_detail');
			exit();
		}
		if (p('ccard') && !(empty($commission_data['become_goodsid'])) && ($commission_data['become_goodsid'] == $goods['id'])) 
		{
			include $this->template('ccard/cmember_detail');
			exit();
		}
		if (p('diypage')) 
		{
			$diypage = p('diypage')->detailPage($goods['diypage']);
			if ($diypage) 
			{
				include $this->template('diypage/detail');
				exit();
			}
		}
		include $this->template();
	}
	public function querygift() 
	{
		global $_W;
		global $_GPC;
		$uniacid = $_W['uniacid'];
		$giftid = $_GPC['id'];
		$gift = pdo_fetch('select * from ' . tablename('ewei_shop_gift') . ' where uniacid = ' . $uniacid . ' and status = 1 and id = ' . $giftid . ' ');
		show_json(1, $gift);
	}
	protected function getGoodsDispatchPrice($goods) 
	{
		if (!(empty($goods['issendfree']))) 
		{
			return 0;
		}
		if (($goods['type'] == 2) || ($goods['type'] == 3) || ($goods['type'] == 20)) 
		{
			return 0;
		}
		if ($goods['dispatchtype'] == 1) 
		{
			return $goods['dispatchprice'];
		}
		if (empty($goods['dispatchid'])) 
		{
			$dispatch = m('dispatch')->getDefaultDispatch($goods['merchid']);
		}
		else 
		{
			$dispatch = m('dispatch')->getOneDispatch($goods['dispatchid']);
		}
		if (empty($dispatch)) 
		{
			$dispatch = m('dispatch')->getNewDispatch($goods['merchid']);
		}
		$areas = iunserializer($dispatch['areas']);
		if (!(empty($areas)) && is_array($areas)) 
		{
			$firstprice = array();
			foreach ($areas as $val ) 
			{
				$firstprice[] = $val['firstprice'];
			}
			array_push($firstprice, m('dispatch')->getDispatchPrice(1, $dispatch));
			$ret = array('min' => round(min($firstprice), 2), 'max' => round(max($firstprice), 2));
		}
		else 
		{
			$ret = m('dispatch')->getDispatchPrice(1, $dispatch);
		}
		return $ret;
	}
	public function get_detail() 
	{
		global $_W;
		global $_GPC;
		$id = intval($_GPC['id']);
		$goods = pdo_fetch('select * from ' . tablename('ewei_shop_goods') . ' where id=:id and uniacid=:uniacid limit 1', array(':id' => $id, ':uniacid' => $_W['uniacid']));
		exit(m('ui')->lazy($goods['content']));
	}
	public function get_comments() 
	{
		global $_W;
		global $_GPC;
		$id = intval($_GPC['id']);
		$percent = 100;
		$params = array(':goodsid' => $id, ':uniacid' => $_W['uniacid']);
		$count = array('all' => pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order_comment') . ' where goodsid=:goodsid and level>=0 and deleted=0 and checked=0 and uniacid=:uniacid', $params), 'good' => pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order_comment') . ' where goodsid=:goodsid and level>=5 and deleted=0 and checked=0 and uniacid=:uniacid', $params), 'normal' => pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order_comment') . ' where goodsid=:goodsid and level>=2 and level<=4 and deleted=0 and checked=0 and uniacid=:uniacid', $params), 'bad' => pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order_comment') . ' where goodsid=:goodsid and level<=1 and deleted=0 and checked=0 and uniacid=:uniacid', $params), 'pic' => pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order_comment') . ' where goodsid=:goodsid and ifnull(images,\'a:0:{}\')<>\'a:0:{}\' and deleted=0 and checked=0 and uniacid=:uniacid', $params));
		$list = array();
		if (0 < $count['all']) 
		{
			$percent = intval(($count['good'] / ((empty($count['all']) ? 1 : $count['all']))) * 100);
			$list = pdo_fetchall('select nickname,level,content,images,createtime from ' . tablename('ewei_shop_order_comment') . ' where goodsid=:goodsid and deleted=0 and checked=0 and uniacid=:uniacid order by istop desc, createtime desc, id desc limit 2', array(':goodsid' => $id, ':uniacid' => $_W['uniacid']));
			foreach ($list as &$row ) 
			{
				$row['createtime'] = date('Y-m-d H:i', $row['createtime']);
				$row['images'] = set_medias(iunserializer($row['images']));
				$row['nickname'] = cut_str($row['nickname'], 1, 0) . '**' . cut_str($row['nickname'], 1, -1);
			}
			unset($row);
		}
		show_json(1, array('count' => $count, 'percent' => $percent, 'list' => $list));
	}
	public function get_comment_list() 
	{
		global $_W;
		global $_GPC;
		$id = intval($_GPC['id']);
		$level = trim($_GPC['level']);
		$params = array(':goodsid' => $id, ':uniacid' => $_W['uniacid']);
		$pindex = max(1, intval($_GPC['page']));
		$psize = 10;
		$condition = '';
		if ($level == 'good') 
		{
			$condition = ' and level=5';
		}
		else if ($level == 'normal') 
		{
			$condition = ' and level>=2 and level<=4';
		}
		else if ($level == 'bad') 
		{
			$condition = ' and level<=1';
		}
		else if ($level == 'pic') 
		{
			$condition = ' and ifnull(images,\'a:0:{}\')<>\'a:0:{}\'';
		}
		$list = pdo_fetchall('select * from ' . tablename('ewei_shop_order_comment') . ' ' . '  where goodsid=:goodsid and uniacid=:uniacid and deleted=0 and checked=0 ' . $condition . ' order by istop desc, createtime desc, id desc LIMIT ' . (($pindex - 1) * $psize) . ',' . $psize, $params);
		foreach ($list as &$row ) 
		{
			$row['headimgurl'] = tomedia($row['headimgurl']);
			$row['createtime'] = date('Y-m-d H:i', $row['createtime']);
			$row['images'] = set_medias(iunserializer($row['images']));
			$row['reply_images'] = set_medias(iunserializer($row['reply_images']));
			$row['append_images'] = set_medias(iunserializer($row['append_images']));
			$row['append_reply_images'] = set_medias(iunserializer($row['append_reply_images']));
			$row['nickname'] = cut_str($row['nickname'], 1, 0) . '**' . cut_str($row['nickname'], 1, -1);
		}
		unset($row);
		$total = pdo_fetchcolumn('select count(*) from ' . tablename('ewei_shop_order_comment') . ' where goodsid=:goodsid  and uniacid=:uniacid and deleted=0 and checked=0 ' . $condition, $params);
		show_json(1, array('list' => $list, 'total' => $total, 'pagesize' => $psize));
	}
	public function qrcode() 
	{
		global $_W;
		global $_GPC;
		$url = $_W['root'];
		show_json(1, array('url' => m('qrcode')->createQrcode($url)));
	}
}
?>