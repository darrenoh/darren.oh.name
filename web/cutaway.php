<?php

namespace Hp;

//  PROJECT HONEY POT ADDRESS DISTRIBUTION SCRIPT
//  For more information visit: http://www.projecthoneypot.org/
//  Copyright (C) 2004-2021, Unspam Technologies, Inc.
//
//  This program is free software; you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation; either version 2 of the License, or
//  (at your option) any later version.
//
//  This program is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with this program; if not, write to the Free Software
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA
//  02111-1307  USA
//
//  If you choose to modify or redistribute the software, you must
//  completely disconnect it from the Project Honey Pot Service, as
//  specified under the Terms of Service Use. These terms are available
//  here:
//
//  http://www.projecthoneypot.org/terms_of_service_use.php
//
//  The required modification to disconnect the software from the
//  Project Honey Pot Service is explained in the comments below. To find the
//  instructions, search for:  *** DISCONNECT INSTRUCTIONS ***
//
//  Generated On: Mon, 14 Jun 2021 16:24:55 -0400
//  For Domain: darren.oh.name
//
//

//  *** DISCONNECT INSTRUCTIONS ***
//
//  You are free to modify or redistribute this software. However, if
//  you do so you must disconnect it from the Project Honey Pot Service.
//  To do this, you must delete the lines of code below located between the
//  *** START CUT HERE *** and *** FINISH CUT HERE *** comments. Under the
//  Terms of Service Use that you agreed to before downloading this software,
//  you may not recreate the deleted lines or modify this software to access
//  or otherwise connect to any Project Honey Pot server.
//
//  *** START CUT HERE ***

define('__REQUEST_HOST', 'hpr9.projecthoneypot.org');
define('__REQUEST_PORT', '80');
define('__REQUEST_SCRIPT', '/cgi/serve.php');

//  *** FINISH CUT HERE ***

interface Response
{
    public function getBody();
    public function getLines(): array;
}

class TextResponse implements Response
{
    private $content;

    public function __construct(string $content)
    {
        $this->content = $content;
    }

    public function getBody()
    {
        return $this->content;
    }

    public function getLines(): array
    {
        return explode("\n", $this->content);
    }
}

interface HttpClient
{
    public function request(string $method, string $url, array $headers = [], array $data = []): Response;
}

class ScriptClient implements HttpClient
{
    private $proxy;
    private $credentials;

    public function __construct(string $settings)
    {
        $this->readSettings($settings);
    }

    private function getAuthorityComponent(string $authority = null, string $tag = null)
    {
        if(is_null($authority)){
            return null;
        }
        if(!is_null($tag)){
            $authority .= ":$tag";
        }
        return $authority;
    }

    private function readSettings(string $file)
    {
        if(!is_file($file) || !is_readable($file)){
            return;
        }

        $stmts = file($file);

        $settings = array_reduce($stmts, function($c, $stmt){
            list($key, $val) = \array_pad(array_map('trim', explode(':', $stmt)), 2, null);
            $c[$key] = $val;
            return $c;
        }, []);

        $this->proxy       = $this->getAuthorityComponent($settings['proxy_host'], $settings['proxy_port']);
        $this->credentials = $this->getAuthorityComponent($settings['proxy_user'], $settings['proxy_pass']);
    }

    public function request(string $method, string $uri, array $headers = [], array $data = []): Response
    {
        $options = [
            'http' => [
                'method' => strtoupper($method),
                'header' => $headers + [$this->credentials ? 'Proxy-Authorization: Basic ' . base64_encode($this->credentials) : null],
                'proxy' => $this->proxy,
                'content' => http_build_query($data),
            ],
        ];

        $context = stream_context_create($options);
        $body = file_get_contents($uri, false, $context);

        if($body === false){
            trigger_error(
                "Unable to contact the Server. Are outbound connections disabled? " .
                "(If a proxy is required for outbound traffic, you may configure " .
                "the honey pot to use a proxy. For instructions, visit " .
                "http://www.projecthoneypot.org/settings_help.php)",
                E_USER_ERROR
            );
        }

        return new TextResponse($body);
    }
}

trait AliasingTrait
{
    private $aliases = [];

    public function searchAliases($search, array $aliases, array $collector = [], $parent = null): array
    {
        foreach($aliases as $alias => $value){
            if(is_array($value)){
                return $this->searchAliases($search, $value, $collector, $alias);
            }
            if($search === $value){
                $collector[] = $parent ?? $alias;
            }
        }

        return $collector;
    }

    public function getAliases($search): array
    {
        $aliases = $this->searchAliases($search, $this->aliases);
    
        return !empty($aliases) ? $aliases : [$search];
    }

    public function aliasMatch($alias, $key)
    {
        return $key === $alias;
    }

    public function setAlias($key, $alias)
    {
        $this->aliases[$alias] = $key;
    }

    public function setAliases(array $array)
    {
        array_walk($array, function($v, $k){
            $this->aliases[$k] = $v;
        });
    }
}

abstract class Data
{
    protected $key;
    protected $value;

    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    public function key()
    {
        return $this->key;
    }

    public function value()
    {
        return $this->value;
    }
}

class DataCollection
{
    use AliasingTrait;

    private $data;

    public function __construct(Data ...$data)
    {
        $this->data = $data;
    }

    public function set(Data ...$data)
    {
        array_map(function(Data $data){
            $index = $this->getIndexByKey($data->key());
            if(is_null($index)){
                $this->data[] = $data;
            } else {
                $this->data[$index] = $data;
            }
        }, $data);
    }

    public function getByKey($key)
    {
        $key = $this->getIndexByKey($key);
        return !is_null($key) ? $this->data[$key] : null;
    }

    public function getValueByKey($key)
    {
        $data = $this->getByKey($key);
        return !is_null($data) ? $data->value() : null;
    }

    private function getIndexByKey($key)
    {
        $result = [];
        array_walk($this->data, function(Data $data, $index) use ($key, &$result){
            if($data->key() == $key){
                $result[] = $index;
            }
        });

        return !empty($result) ? reset($result) : null;
    }
}

interface Transcriber
{
    public function transcribe(array $data): DataCollection;
    public function canTranscribe($value): bool;
}

class StringData extends Data
{
    public function __construct($key, string $value)
    {
        parent::__construct($key, $value);
    }
}

class CompressedData extends Data
{
    public function __construct($key, string $value)
    {
        parent::__construct($key, $value);
    }

    public function value()
    {
        $url_decoded = base64_decode(str_replace(['-','_'],['+','/'],$this->value));
        if(substr(bin2hex($url_decoded), 0, 6) === '1f8b08'){
            return gzdecode($url_decoded);
        } else {
            return $this->value;
        }
    }
}

class FlagData extends Data
{
    private $data;

    public function setData($data)
    {
        $this->data = $data;
    }

    public function value()
    {
        return $this->value ? ($this->data ?? null) : null;
    }
}

class CallbackData extends Data
{
    private $arguments = [];

    public function __construct($key, callable $value)
    {
        parent::__construct($key, $value);
    }

    public function setArgument($pos, $param)
    {
        $this->arguments[$pos] = $param;
    }

    public function value()
    {
        ksort($this->arguments);
        return \call_user_func_array($this->value, $this->arguments);
    }
}

class DataFactory
{
    private $data;
    private $callbacks;

    private function setData(array $data, string $class, DataCollection $dc = null)
    {
        $dc = $dc ?? new DataCollection;
        array_walk($data, function($value, $key) use($dc, $class){
            $dc->set(new $class($key, $value));
        });
        return $dc;
    }

    public function setStaticData(array $data)
    {
        $this->data = $this->setData($data, StringData::class, $this->data);
    }

    public function setCompressedData(array $data)
    {
        $this->data = $this->setData($data, CompressedData::class, $this->data);
    }

    public function setCallbackData(array $data)
    {
        $this->callbacks = $this->setData($data, CallbackData::class, $this->callbacks);
    }

    public function fromSourceKey($sourceKey, $key, $value)
    {
        $keys = $this->data->getAliases($key);
        $key = reset($keys);
        $data = $this->data->getValueByKey($key);

        switch($sourceKey){
            case 'directives':
                $flag = new FlagData($key, $value);
                if(!is_null($data)){
                    $flag->setData($data);
                }
                return $flag;
            case 'email':
            case 'emailmethod':
                $callback = $this->callbacks->getByKey($key);
                if(!is_null($callback)){
                    $pos = array_search($sourceKey, ['email', 'emailmethod']);
                    $callback->setArgument($pos, $value);
                    $this->callbacks->set($callback);
                    return $callback;
                }
            default:
                return new StringData($key, $value);
        }
    }
}

class DataTranscriber implements Transcriber
{
    private $template;
    private $data;
    private $factory;

    private $transcribingMode = false;

    public function __construct(DataCollection $data, DataFactory $factory)
    {
        $this->data = $data;
        $this->factory = $factory;
    }

    public function canTranscribe($value): bool
    {
        if($value == '<BEGIN>'){
            $this->transcribingMode = true;
            return false;
        }

        if($value == '<END>'){
            $this->transcribingMode = false;
        }

        return $this->transcribingMode;
    }

    public function transcribe(array $body): DataCollection
    {
        $data = $this->collectData($this->data, $body);

        return $data;
    }

    public function collectData(DataCollection $collector, array $array, $parents = []): DataCollection
    {
        foreach($array as $key => $value){
            if($this->canTranscribe($value)){
                $value = $this->parse($key, $value, $parents);
                $parents[] = $key;
                if(is_array($value)){
                    $this->collectData($collector, $value, $parents);
                } else {
                    $data = $this->factory->fromSourceKey($parents[1], $key, $value);
                    if(!is_null($data->value())){
                        $collector->set($data);
                    }
                }
                array_pop($parents);
            }
        }
        return $collector;
    }

    public function parse($key, $value, $parents = [])
    {
        if(is_string($value)){
            if(key($parents) !== NULL){
                $keys = $this->data->getAliases($key);
                if(count($keys) > 1 || $keys[0] !== $key){
                    return \array_fill_keys($keys, $value);
                }
            }

            end($parents);
            if(key($parents) === NULL && false !== strpos($value, '=')){
                list($key, $value) = explode('=', $value, 2);
                return [$key => urldecode($value)];
            }

            if($key === 'directives'){
                return explode(',', $value);
            }

        }

        return $value;
    }
}

interface Template
{
    public function render(DataCollection $data): string;
}

class ArrayTemplate implements Template
{
    public $template;

    public function __construct(array $template = [])
    {
        $this->template = $template;
    }

    public function render(DataCollection $data): string
    {
        $output = array_reduce($this->template, function($output, $key) use($data){
            $output[] = $data->getValueByKey($key) ?? null;
            return $output;
        }, []);
        ksort($output);
        return implode("\n", array_filter($output));
    }
}

class Script
{
    private $client;
    private $transcriber;
    private $template;
    private $templateData;
    private $factory;

    public function __construct(HttpClient $client, Transcriber $transcriber, Template $template, DataCollection $templateData, DataFactory $factory)
    {
        $this->client = $client;
        $this->transcriber = $transcriber;
        $this->template = $template;
        $this->templateData = $templateData;
        $this->factory = $factory;
    }

    public static function run(string $host, int $port, string $script, string $settings = '')
    {
        $client = new ScriptClient($settings);

        $templateData = new DataCollection;
        $templateData->setAliases([
            'doctype'   => 0,
            'head1'     => 1,
            'robots'    => 8,
            'nocollect' => 9,
            'head2'     => 1,
            'top'       => 2,
            'legal'     => 3,
            'style'     => 5,
            'vanity'    => 6,
            'bottom'    => 7,
            'emailCallback' => ['email','emailmethod'],
        ]);

        $factory = new DataFactory;
        $factory->setStaticData([
            'doctype' => '<!DOCTYPE html>',
            'head1'   => '<html><head>',
            'head2'   => '<title>Darren.oh.name:strategic Collegiate</title></head>',
            'top'     => '<body><div align="center">',
            'bottom'  => '</div></body></html>',
        ]);
        $factory->setCompressedData([
            'robots'    => 'H4sIAAAAAAAAA7PJTS1JVMhLzE21VSrKT8ovKVZSSM7PK0nNK7FVSsvPyckv18nLTyxKzsgsS1Wys8GnPC8_My8ltULJDgBGxg-KVQAAAA',
            'nocollect' => 'H4sIAAAAAAAAA7PJTS1JVMhLzE21VcrL103NTczM0U3Oz8lJTS7JzM9TUkjOzytJzSuxVdJXsgMAKsBXli0AAAA',
            'legal'     => 'H4sIAAAAAAAAA8Vba3PbuJL9vr8C62zlJlWOY2ec2FlmXKU4mkRbGTllKUnNR5CERExIgAOQ1mh__Ta6G3zIipK717WbKskSReLROH36dAN508i0VCJTZVnLPNdm_evR6ZFIrcuVw4_hJ1_LjH-6-rc3jQtv-dWblTWN8M22VL8ehc_PVrLS5fY_RWZbp5U7FpU1NjyskqOrxyb1dSLoD395kzq-Hj4tCyUePzo7e43fGiu2Nnyw4dqrBN5PzxPl8JlHlxd44SxJvQ5XnJJv_v3ZM1FLp0wjSwHjdXZjYHxOmrUSTWtyJ8WzZ1c5divXcB_2U6jqhBo9OztNrHmGl7-Omh8OlCee2dK6Xx_9hv-uVm-eh6tXb56nVwemCAP_JanV40evX-P4X-LVOXT6-NHFC_ziwzheJDpX4VvtwvRPf0lw3rJtbBXHM62kLsOHWSO0x5_L8OxZYvDRQvp9I7U8UomdNdbhhztZtipe6Ucm106p2OF3pt7Eqe_5Tf-cWaYyK3hVJHVucDUr7ZXzYdHCj0-OwEjnSZvzsu3r8Bt3iObybUao2XenikML3Zpc1Ii1lVgpWqjXF4nZCjTOQgW7nid3mnomaNBv_UREN7W9psgGprgHiteJxQn7Rjawxm4rbPqnyhp9p8LsQ_cXicjC3ZXc7mu-Hi6rTFuP0_iivW66YT66fJXMEIKzZbjwaXK7_AMn-PntYoq3xTGCf__vnfywIfw9TMQBqpRHy_P1bdpbqlEOod8o5oXguAgaL9BHXgxRBbM8T4BPkBuk8St8Am2sy9KAlZ3KxUZCq9tgYXWydzEHaCUHEw7t2lYEEGrfHwscqKy8QHDKRmc76AB2df03pzKcw4n4w7b95eCF4dtaHf_QdekJACxazKxFUyA8cI6mbZqt2CrpSiBCkdsWaB5cOce5QsM_M1siIpyoa7ZEDtyLzrQs-R5whbX2OIpSEn97bIO9ZmVx4hBQ4iNAfo22JnzJ4KbzV4lAGKZI9ZeJ-qnhWQMYR6eBKUIQq2RVwQIBKVZhhrVTd1ptovucIsrqGkxChodog60Av3RLdXO7EJO-35tbsWDHOUs-T-fL_ysX-fZ9FxEEdYfwAYqg4Mh-sCb-v0OI7mt4HRGUIZdktLhqxf4kBsyfZQqDCixbWvYh4F-JgwQhp7MRNdkG_Ad78tT9GpFRltvOJ5X3NBiKhUb_txq6Oswh-0YRgz06h7XPQNfIprClzkSjnfK2UjGUKGEs-U3-nOBZIFpfMva0F19VjAw_CoD3V-ufp76DlL525GY0cksuSM9P2JtopRRrG3K8vQHSDzmkonZ5ikxUJh_waOqIn-xeOEVCIjIbiYWfiomrnZiIMBSg_IarG1YFlm546eKXJFeIkmianjLe3z5-9Op1MsUL0_m72bx_8u3tdIIwyVWtDDQBGFkBMgqxKuEdB0cc-RCe_p1JF_fhsuMjldgUJDUs861yRuN8Vc0Oj-hto5MQw-XkPyRTvHB6TYjZ9BqAYbwASjtNptezkeWBk9HLLOHHn5AvgKH-xoZy0RDB3KcDSyOgYcF9BeLLbkpGWH8nGNrjGqykA4UOQd6twF6N5Q_R-nR3rgkA2G6IoYiDlC7qhrXa2YvTBPje_2RUgwH5zrCCDGvN0VO8Z5JXdKWm-Fdq7MQECiECo0zDqRK4Joe_6xY-Bc32Vyuzb8LIO5QU3eCAsYqGggyELDIUzifEqZ_SHYW8ozFtCkWjaPRK96RgOEfaBzfJcCPxA0Ms4VEY91qWoA3A4LaFCLgG3_XeusCcqrT1wAvgtfwwWeJg3gsgnIvLBCVruHD9AWLGRUhmLijC_r85DWQQAQhniSXQr1AUXuKg1oID3pjlmUINPYeXcrDPE7yzoTsF-lBhSZL2wokdafJxyOVXH0cOxa5haO1DlvcBx8budTwYhEQez9Sw-ULvTeAiaXpiS1p5EjPrEMGDLh3zJ_wsTaSJ80SnPBmEMxvEow4LV_6xt9eYySkU4b5km9X_Mt-TN6W2RMSBnwwGn3E-i5d8AYil4NxQ0prHUFFuuXDQ65dRAy1NHE0kNZEcZxVWqJ0l_Sfz2Kvb4WzWkJy0o7VHKwPLkCa2gjOz8Powuf0y7W_-fbpY_EgLLLFOMF08hKv1U1hOR5Noo7twPWHDsYegwn5BxRNIytCac-JEvS6A-iFg5DAMBRrL1qC-YF0tONl5eDZWOZ5CjEPLEGCbnswioO8tzPV0jsnrdDjWQ4LsTlP1pq0kZ2o2tUPUHws37APiCbmq3yudInppqOGdODAH-hzkdp8Xh6TSf2BHgAvbPzEzFCukJsqiQTgyfqHTId98-vSRCl1OVbCiJHiBtRvIe8DquYXoJbF3WwbDjwhpS1WVrYf8VNSyhidDeoSD8EQKmleaClSwENr-yMypIpIM1YqDqjclaRqqWSQUqZTykvPoxoNSIVIrdBWDJ6SDnOS4HTygzrvG8HP7bjhNJ1AiUn0PWJRYiuZn9F8D_2wK3YmGxRKfmr9HFfB--gSdbHqoloNVhim8v3io2Pf7YhRPJvPhvEIalBPLZLZPaoNIHLjPSMV30CcwwXNmxJx_k8gZaKe-jcBt08VyJBGpGaLTf-AzXR4XXSpzcenOEpkfjyKmg5yMKl0b61yQISA1XMBrYI2QoQGV-xYdcSO_dWKQq4IleYZSKERPidFfnp6IPyiI6D_bNfiDqMEX4qMr0VBwpAQR-0vp51iEQN18Go0Anecn_aCvridvP44opzkGfsQWo7K2d5xV-CyUHu7kusUOQLaTLV5huZotF9RfuL1QQSnmaqUyAAkos9wBL8BjlJspwxKEPCWE9lfxizYD7QBfcDjWgFfv-MhBLkLX-W1PkcEZ1Qy8lcF1oIiKkimsXTWAIFe8wutYvJ9g9eSpWN7gGH9fiJuHFI1gHNB6Q98hZpjfF2TR6XPd6AELf1Up1xiA-0K8CphAPvpC6GOcN65bSiCnWJYIwpPLlsgvwGVO7yzG7ex6uUcgljrrSlGaR3CRbEra60BPh0hTj1oznjcIQkpExX-UgsHnsC0ey4r-UlATMu9WFaIMTTSGQwppIbL3Imow9kOi_OPk676f833ZfQh9kR_OE2HdoTJVTyR5GO1lsu0WqBFPmt6EqaIQsUGTVOweXaV-Qu5hVEaGcFRn3F2fL7PFbHl_fT65mF6lnTrhho_I8rzNoDh2BivuFdFxXqvYwQcKHlSjWH7AiHbIlLPvxeLoiAtq8MsDedW72XJ2c98iodJDKKTq7ZJ5kwoGYY175orFBBKMQJ0tF95AfODdoBN3WetmPg6DRBSDYZiYxnWR5zTJStoZinGIShDA9Ly18k03gWytqyDsZKVt8y0Xf1oPWV8g3pHCcIoogNSD53U9tKzot-izhaA8yTUF7TmYOxV21kKI0GsTYhSGB4RrcGCUdHaj3KothWvB98Xa2RaiOLyvQ3B0SsKAYX18Q7INvb4sIXgQ3zhC98CMGDDm70am_K-hGWFANW0sOpbKkSHVwEkj1icUe80hbYz3LBpJgrb3RD0Mf3gpo-L9upR5L0J5I2cXDreHIP95MZoS3ITtz3Le4AWtw6UPbf5qlTEaFrs1mXKgmU2zFWvpcMcg1z7TdalNt-fGRQGGMKem4fX-WCwQ9O8wJzlPZmQu0I_X05MHcr77TsDRuovTXL9locuVLVq6t5QSM8lRI0-aAU_ZqIuKEHd6auNbBE4rXAbBVO0NCTe3o_HNcYCjtQCdhGSNgz0WaRu7OU-CP_QjA9VWjHzP1o739SNCNYu_MQOxVwryIAtLZ6RzoRwHIhAkFZh8xd6GnQM1HXdrSlsJO7P6fDtbdGKiR5Ui5W3WwBnoeHJDKVyH8e5Dt6kHfkA2jZXAs9eJboqBdKud3XXa6TgBwHLbKLuRREt5LPKHWa00cckWoITNb8NGZTBJJn0D9LNy8Ne1Wdj3JHQzVYXXze2Msp7fZhiB9rrbahCTHwLgA1xfvkwW0_sylEF7RNDoSg0gw_SgRnS0VIPkO4o4WH8bYwM4BSI44AQb4H0ma3ZMP3_24fPvOwmX6EN_c6gyxDtpHSbPQ0J9fk49wgjDWqjUmm1IwXJYCGATXQFQe_KjXZSu70a6LacVqha6Aj_ETAHQHfKjrADObjSkd74GLdgfk-BNVJrjjkhUsTKf-obYrJDujkadC-l3DIJ6FYNn4dRGQ6iUqXXxVMJIGIRt5rRzrNcJ19e77KuLCd2HQ5weC384oJNDd7KrpRAeM16yQ1J1t4ZwMJa9HYcW73n3_-jpoaKARnU340yPwOmt6VJKgsXyljaaQ2oIcqdj24eJHQyhQ2IFPGVAp46ixlo8IaT-XStMj4BNtyJVINoQuI3TKZ1PAf4FzlbSNbRVwIWc0EjVHchAYHBpT-BaFqS9X1NYsiux-CEW4hGHwapN5ofS2i-jHAukzNfB5qGxVMMnUdi6FBwIXAlzZ_AxQHeL8mwDPqW2MLfab7PCytFZnE4LmIZFxpiztOk3f0Du8AkIH31hL5HE6bJi9HY1NC9v-nAF3JOeOk3W3zNePJQlxd1uljPfw7Q4J9vyZlXPm2JEruF00vfxHzeaVoOcmngvYLPY5s7-HcJS3aaQ8YLZy0AmtHPmWySkMEEQvDRjEqQcxXh7ADodqIaM9yvJYoVeFzaDVoAbtyHoNbIM9MjgDHt-tDUEei8_FHTpamZdvltVI-gqEZN-WAEV0u7L05E2Cu4MCcv0Folg-XlJed0DezfEQYX5J2OBs_tY_9sOZMZCuTsi-5LEsQ_HZWLNCoSw8CpzMJW498rH3aguAR5kNzsGg0T5ZrRnkHJQJeCXuqJiyXZ8XGHlbF83j6-Sy7yDonP_REEFjGqg_WKMP1STzmxVU_85lZL3HqiKafWacG1LjOLNjsdw5eIQ56eP-y3EQH8rCrZAXb05D4UlJoaQspB8iVShIuK7QBoCrdw3wPxETJCUWj5CSujFqOgsV25HapNrD-95kJe_JGIy73PxB8IpxLuzWFQaqLCu-lZJYzq2fJlkCuglAApLxXz5IlFeDOr23QmXwMGst4QdW-Rq8T1JKeuaTiCUYb8KEALZhKxEOCdoK-FL6UPCnbYOyWNwzi1nNUg84uIARzlzfLV9mS0sBo70UNSiEnLWljWWp6G5VSNKuzEq-zYMsb43VUwuDgkphEwpCdPa8HGKoo_FMWqHLanV2ILMM21W7OESUJR6QDhd9TQSJealg8D7Z0sZXQdt4KAlfshiHX73RQ-qSvKuUD2ow0GWL0csvrwREzrAcX2NfPvp4YGMWyWWIiptJ6hae5urUHyBeBF807cg1PEz7kSAoAAfDmcs_LZKaU-u23pGOz6NW_jh24n4RLDpQ09X6o01r36ZVqPd_3vmI05q2IuC6tyQ6KvqlpYmrNqI7VzYH0GoMGE44iPT0CJA-ozYZ0cebMOsj0db98OdteGrlnyWlIMLnsGiDvnwAoErp1K45zNN2SDs3ykzKCRUHNYpU7tITL6v2_KQYpF53k0KJLumwwXhQMwIYB9mSJjv8Mtkfj19aIDlVGOA2N1TDl6gIwNO7Z3avhcuYTNCRdMfpzTdvCjq8BHIQy8CAwIPHzaEKnZcxYeoVp0Zz5O9w2KTPu5O5pzc64jO5Mzf7Tfuc_zfJ8_xv63AB_j9fwB2ELmRwzIAAA',
            'style'     => 'H4sIAAAAAAAAAyXMywmAMAwA0FUEr9bPtRWP3SPWCIWSlCRgRdzdg2-At6rdBTcY9cpan8SFxfcxxnAymd-5HN0y19aBZCiDAqlTlHwGw2buwMQClpk8MWF41-kPP3WC59tYAAAA',
            'vanity'    => 'H4sIAAAAAAAAA22S207DMAyGX8XKbmEdp0nL2goxDSEk2DTggsu0ydpAiCPHrOztScu44KDIkh3F3_87Sc6qcgZq41wMqra-KcRE9GVQWh_KCkkb6rPIe2cKUan6tSF891qOZrPZvLOaW3l6Ngkfc1HmTCk07JSzjS8EY_huPEAlnIQPOE1xkeI8dX1JHJNtWpYRndXDkdFiseiJyZuHA2OLnmWFTkOvB4qsckdR-XgcDdntvEaHJEfT6XSelGXvKWC0bNFLMk6x3ZnEvMyznlrmGes_duGQO7NlAb_MnyXVSVrnX9MqaMlsC9EyB5llXdeNA-GLqblFb_YBeYzUZAJqp2IsROxsDKK8W95dLTewuob1ZnW7XDzCzep--Qzr1WOeqTKv6F_0u0-u38Y1vomfvIe0DTeKdiayIVgTcrKQhoZ7wx3Sa09MxnZWGw3VHp4G0qA1XEHWP1s2_IfyE3NAqYkXAgAA',
        ]);
        $factory->setCallbackData([
            'emailCallback' => function($email, $style = null){
                $value = $email;
                $display = 'style="display:' . ['none',' none'][random_int(0,1)] . '"';
                $style = $style ?? random_int(0,5);
                $props[] = "href=\"mailto:$email\"";
        
                $wrap = function($value, $style) use($display){
                    switch($style){
                        case 2: return "<!-- $value -->";
                        case 4: return "<span $display>$value</span>";
                        case 5:
                            $id = 'v1r2m5y9';
                            return "<div id=\"$id\">$value</div>\n<script>document.getElementById('$id').innerHTML = '';</script>";
                        default: return $value;
                    }
                };
        
                switch($style){
                    case 0: $value = ''; break;
                    case 3: $value = $wrap($email, 2); break;
                    case 1: $props[] = $display; break;
                }
        
                $props = implode(' ', $props);
                $link = "<a $props>$value</a>";
        
                return $wrap($link, $style);
            }
        ]);

        $transcriber = new DataTranscriber($templateData, $factory);

        $template = new ArrayTemplate([
            'doctype',
            'injDocType',
            'head1',
            'injHead1HTMLMsg',
            'robots',
            'injRobotHTMLMsg',
            'nocollect',
            'injNoCollectHTMLMsg',
            'head2',
            'injHead2HTMLMsg',
            'top',
            'injTopHTMLMsg',
            'actMsg',
            'errMsg',
            'customMsg',
            'legal',
            'injLegalHTMLMsg',
            'altLegalMsg',
            'emailCallback',
            'injEmailHTMLMsg',
            'style',
            'injStyleHTMLMsg',
            'vanity',
            'injVanityHTMLMsg',
            'altVanityMsg',
            'bottom',
            'injBottomHTMLMsg',
        ]);

        $hp = new Script($client, $transcriber, $template, $templateData, $factory);
        $hp->handle($host, $port, $script);
    }

    public function handle($host, $port, $script)
    {
        $data = [
            'tag1' => 'cddf55d4bdf94b90da1447ecbd0fac3e',
            'tag2' => '58527d929560323405de1d3e785a72d1',
            'tag3' => '3649d4e9bcfd3422fb4f9d22ae0a2a91',
            'tag4' => md5_file(__FILE__),
            'version' => "php-".phpversion(),
            'ip'      => $_SERVER['REMOTE_ADDR'],
            'svrn'    => $_SERVER['SERVER_NAME'],
            'svp'     => $_SERVER['SERVER_PORT'],
            'sn'      => $_SERVER['SCRIPT_NAME']     ?? '',
            'svip'    => $_SERVER['SERVER_ADDR']     ?? '',
            'rquri'   => $_SERVER['REQUEST_URI']     ?? '',
            'phpself' => $_SERVER['PHP_SELF']        ?? '',
            'ref'     => $_SERVER['HTTP_REFERER']    ?? '',
            'uagnt'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ];

        $headers = [
            "User-Agent: PHPot {$data['tag2']}",
            "Content-Type: application/x-www-form-urlencoded",
            "Cache-Control: no-store, no-cache",
            "Accept: */*",
            "Pragma: no-cache",
        ];

        $subResponse = $this->client->request("POST", "http://$host:$port/$script", $headers, $data);
        $data = $this->transcriber->transcribe($subResponse->getLines());
        $response = new TextResponse($this->template->render($data));

        $this->serve($response);
    }

    public function serve(Response $response)
    {
        header("Cache-Control: no-store, no-cache");
        header("Pragma: no-cache");

        print $response->getBody();
    }
}

Script::run(__REQUEST_HOST, __REQUEST_PORT, __REQUEST_SCRIPT, __DIR__ . '/phpot_settings.php');

