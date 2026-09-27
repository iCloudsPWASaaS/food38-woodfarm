<template>
    <Swiper dir="ltr" :speed="1000" slidesPerView="auto" :spaceBetween="16" class="menu-slides">
        <SwiperSlide v-for="category in categories" :key="category" class="!w-fit">
            <div class="w-32 flex flex-col items-center text-center gap-2">
                <router-link v-if="design === categoryDesignEnum.FIRST"
                    :to="{ name: 'frontend.menu', query: { s: category.slug } }"
                    class="w-full aspect-square rounded-2xl border-b-2 border-transparent transition hover:bg-[#FFEDF4] bg-[#F7F7FC] overflow-hidden">
                    <img class="w-full h-full object-cover" :src="category.thumb" alt="category">
                </router-link>

                <router-link :class="checkIsQueryAndRouteQuerySame(category.slug) ? 'menu-category-active' : ''"
                    v-else-if="design === categoryDesignEnum.SECOND"
                    :to="{ name: 'frontend.menu', query: { s: category.slug } }"
                    class="w-full aspect-square rounded-2xl border-b-2 border-transparent transition hover:bg-[#FFEDF4] overflow-hidden">
                    <img class="w-full h-full object-cover" :src="category.thumb" alt="category">
                </router-link>

                <h3 class="text-xs font-medium">{{ category.name }}</h3>
            </div>
        </SwiperSlide>
    </Swiper>
</template>

<script>

import categoryDesignEnum from "../../../enums/modules/categoryDesignEnum";
import { Swiper, SwiperSlide } from 'swiper/vue';
import 'swiper/css';

export default {
    name: "CategoryComponent",
    props: {
        categories: Object,
        design: Number
    },
    components: {
        Swiper,
        SwiperSlide,
    },
    data() {
        return {
            currentCategory: "",
            categoryDesignEnum: categoryDesignEnum,
        }
    },
    mounted() {
        if (this.$route.query.s !== "undefined") {
            this.currentCategory = this.$route.query.s;
        }
    },
    methods: {
        submit: function (msg, e) {
            e.stopPropagation()
        },
        checkIsQueryAndRouteQuerySame(query) {
            if (this.currentCategory === query) {
                return true;
            }
        },
    },
    watch: {
        $route(to, from) {
            if (to.query.s !== "undefined") {
                this.currentCategory = to.query.s;
            }
        }
    }
}
</script>
